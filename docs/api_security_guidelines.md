# Hướng dẫn Bảo mật & Phân quyền API (API Security & Authorization Guidelines)

Tài liệu này quy định các nguyên tắc thiết kế bảo mật, phân chia ranh giới phân quyền (API Boundary) và hướng dẫn lập trình an toàn cho hệ thống API của Nhà Hàng nhằm đảm bảo bảo mật dữ liệu khách hàng và ngăn ngừa truy cập trái phép.

---

## 1. Phân loại Cấp độ API (API Authorization Tiers)

Hệ thống API được chia làm 3 cấp độ phân quyền nghiêm ngặt:

| Cấp độ | Vị trí thư mục | Yêu cầu Xác thực | Yêu cầu CSRF | Mô tả |
| :--- | :--- | :--- | :--- | :--- |
| **Public API** | `api/public/`<br>`api/menu/`<br>`api/bookings/`<br>`api/tables/` | Không yêu cầu | Không | Các API công khai phục vụ khách hàng duyệt món, xem bàn trống, đánh giá 5 sao công khai. |
| **Customer API** | `api/user/`<br>`api/payment/` | Yêu cầu Đăng nhập<br>(Customer/Admin) | **Có** (cho mutating requests) | Các API cá nhân của khách hàng như cập nhật hồ sơ, đặt bàn, xem lịch sử mua hàng, thanh toán. |
| **Admin API** | `api/admin/` | Yêu cầu Quyền Admin | **Có** (cho mutating requests) | Các API quản trị hệ thống như thêm/sửa món ăn, quản lý người dùng, xem doanh thu, xem log. |

---

## 2. Nguyên tắc Bảo mật cho từng Cấp độ

### 2.1. Đối với Public API
- **Không tiết lộ thông tin nhạy cảm:** Tuyệt đối không trả về thông tin cá nhân khách hàng (Họ tên, SĐT, Email, Địa chỉ) hoặc các dữ liệu quản trị (mã hóa đơn, thông tin nội bộ).
- **Chỉ đọc dữ liệu hợp lệ:** Chỉ truy vấn các bản ghi đang hoạt động (`is_active = 1`) và chưa bị xóa mềm (`deleted_at IS NULL`).
- **Phòng chống Spam & Brute Force:** Đối với các API xử lý kiểm tra mã (như `check_voucher.php`), cần xem xét giới hạn truy cập (Rate Limit) để tránh brute force.

### 2.2. Đối với Customer API
- **Ngăn ngừa IDOR (Insecure Direct Object Reference):**
  - **Bắt buộc** lấy `user_id` trực tiếp từ Session (`AuthStateService::requireSession()`), không tin tưởng `user_id` truyền từ Client qua tham số `POST` hoặc `GET`.
  - Khi truy vấn hoặc thay đổi dữ liệu (như chi tiết hóa đơn), bắt buộc so sánh quyền sở hữu:
    ```php
    $stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $orderId, $userId);
    ```
- **Xác thực CSRF:** Đối với tất cả các Request thay đổi dữ liệu (`POST`/`PUT`/`DELETE`), bắt buộc gọi:
  ```php
  CsrfService::validateRequest();
  ```
- **Giới hạn tần suất (Rate Limiting):** Bắt buộc sử dụng dịch vụ giới hạn tần suất cho các hành động nhạy cảm (đổi mật khẩu, đặt bàn, gửi review):
  ```php
  RateLimitService::check('book_table_' . $userId, 5, 60);
  ```

### 2.3. Đối với Admin API
- **Khóa bảo vệ ở đầu file:** Tất cả các file API quản trị bắt buộc phải include bộ kiểm tra quyền admin ở ngay đầu file:
  ```php
  require_once __DIR__ . '/auth_check_api.php';
  ```
  Bộ kiểm tra này sẽ tự động xác thực phiên và đảm bảo chỉ tài khoản có vai trò `admin` trong Database mới được tiếp tục thực thi.
- **Không đặt API quản trị ngoài thư mục quy định:** Toàn bộ API quản trị phải nằm trong thư mục `api/admin/`. Trường hợp ngoại lệ đặc biệt (như `api/menu/get_menu_list.php`) bắt buộc phải được bảo vệ bằng `auth_check_api.php` và được liệt kê cụ thể trong tài liệu này.

---

## 3. Hướng dẫn Lập trình An toàn (Secure Coding Practices)

### 3.1. Chống SQL Injection
- **Không bao giờ** ghép chuỗi trực tiếp các tham số từ người dùng vào câu lệnh SQL.
- **Luôn sử dụng Prepared Statements** cho tất cả các câu truy vấn động:
  ```php
  $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  ```

### 3.2. An toàn tải lên File (File Upload Security)
- **Không tin tưởng thông tin từ Client:** Không sử dụng tên file gốc hoặc extension do người dùng gửi lên.
- **Kiểm tra MIME type thực tế:** Sử dụng `finfo` để kiểm tra MIME type từ nội dung file thực tế thay vì phần mở rộng.
- **Sử dụng Safe Mapping để lấy Extension:** Chỉ chấp nhận các MIME type được khai báo sẵn trong whitelist và ánh xạ ra extension an toàn tương ứng.
- **Xóa file cũ an toàn:** Khi cập nhật ảnh mới, xóa file ảnh cũ để tránh lãng phí dung lượng lưu trữ nhưng phải đảm bảo đường dẫn file được kiểm soát, không để xảy ra Path Traversal.

### 3.3. Ghi nhật ký (Logging)
- Ghi log tất cả các hành động nhạy cảm hoặc thất bại bằng `LoggerService` để phục vụ audit:
  - Lỗi đăng nhập hoặc phát hiện truy cập trái phép: `Logger::security()`, `Logger::auth()`.
  - Thanh toán thành công/thất bại: `Logger::payment()`.
- **Không bao giờ lộ lỗi SQL hoặc hệ thống nội bộ ra ngoài:** Trả về thông báo lỗi thân thiện cho client, ghi chi tiết lỗi vào log file phía máy chủ.
