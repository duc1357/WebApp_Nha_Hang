# Architecture Decision Records (ADR)
## Cơm Quê Dượng Bầu – Restaurant Web Application

> Tài liệu này ghi lại các quyết định kiến trúc quan trọng, lý do chọn lựa, và hướng phát triển dài hạn.

---

## ADR-001: Zero-Dependency PHP (Không dùng Framework)

**Ngày:** 2026-04  
**Trạng thái:** Active

### Quyết định
Dự án sử dụng PHP thuần, không có composer/framework.

### Lý do
- Yêu cầu triển khai trên shared hosting (không hỗ trợ composer)
- Team đã quen với PHP thuần
- Scope nhỏ: không cần DI container, routing phức tạp

### Hệ quả
- **Ưu:** Không phụ thuộc vendor, deploy đơn giản (FTP/Git)
- **Nhược:** Phải tự viết middleware, validation, routing
- **Giảm thiểu:** Tạo `api/base.php` làm micro-framework nội bộ

### Khi nào nên xem xét lại?
Nếu có **>3 developers** hoặc cần **REST API chuẩn** cho mobile app → Cân nhắc **Slim Framework 4** hoặc **Laravel Lumen** vì:
- PSR-7/PSR-15 middleware chuẩn
- Dependency Injection Container
- Route groups với middleware
- Không nặng như Laravel full-stack

---

## ADR-002: Session-Based Auth (Web) + JWT (API)

**Ngày:** 2026-04  
**Trạng thái:** Active (Hybrid approach)

### Quyết định
- **Web frontend:** PHP Session (đã cài CSRF protection)
- **API / Mobile (tương lai):** JWT via `api/services/jwt_service.php`

### Lý do
- Session đủ an toàn cho web browser (với HttpOnly cookie)
- JWT cần thiết cho stateless API (mobile app, third-party)
- Không cần thêm thư viện: JWT tự implement HS256 (RFC 7519)

### Cấu hình JWT
```
JWT_SECRET = [64-char hex] (trong .env)
JWT_TTL    = 7 ngày (configurable)
Algorithm  = HS256 (HMAC-SHA256)
```

### Luồng JWT
```
Client → POST /api/auth/login.php → nhận JWT token
Client → gửi kèm header: Authorization: Bearer <token>
Server → JwtService::requireToken() verify và extract payload
Client → Trước khi hết hạn: POST /api/auth/refresh_token.php
```

### JWT Revocation (Token vô hiệu hóa)
Khi cần vô hiệu hóa token (logout, đổi mật khẩu):
1. Tăng `users.token_version` trong DB
2. JWT payload phải chứa `token_version` hiện tại
3. `requireToken()` so sánh payload version với DB version

---

## ADR-003: Structured JSON Logging

**Ngày:** 2026-04  
**Trạng thái:** Active

### Quyết định
Dùng `api/services/logger_service.php` thay vì `error_log()` thuần.

### Cấu trúc Log Entry (NDJSON)
```json
{
  "timestamp": "2026-04-29T10:30:00+07:00",
  "level": "INFO",
  "channel": "auth",
  "message": "Admin login success",
  "context": { "user_id": 1, "email": "admin.demo@example.test" },
  "request": { "ip": "1.2.3.4", "method": "POST", "uri": "/api/admin/login.php" }
}
```

### Log Channels
| Channel    | Mục đích                          | File              |
|------------|-----------------------------------|-------------------|
| `app`      | General application events        | `logs/app.log`    |
| `auth`     | Login/logout/register events      | `logs/auth.log`   |
| `payment`  | Payment/webhook events            | `logs/payment.log`|
| `security` | Suspicious activity, brute-force  | `logs/security.log`|

### Log Rotation
- Max file size: **5MB**
- Backup files giữ: **7 bản**
- Pattern: `auth.log` → `auth.log.1` → ... → `auth.log.7`

### Bảo mật
- Thư mục `logs/` bị block bởi `.htaccess` (không truy cập từ web)
- File locking (`LOCK_EX`) ngăn race condition khi ghi

---

## ADR-004: Database Migration System

**Ngày:** 2026-04  
**Trạng thái:** Active

### Quyết định
Tự build migration runner (`Database/migrate.php`) thay vì dùng Phinx/Doctrine Migrations.

### Lý do
- Zero-dependency requirement (ADR-001)
- Chỉ cần feature cơ bản: up/down/status/rollback
- Tracking bằng bảng `_migrations` trong DB

### Quy ước đặt tên migration
```
YYYY_MM_DD_HHMMSS_ten_ngan_gon.php
Ví dụ: 2026_04_29_120000_add_token_version_to_users.php
```

### Chạy migration
```bash
# Từ thư mục root
php Database/migrate.php            # Run pending
php Database/migrate.php status     # Xem trạng thái
php Database/migrate.php rollback   # Rollback batch cuối
```

---

## ADR-005: Frontend Module System

**Ngày:** 2026-04  
**Trạng thái:** Active

### Quyết định
Tách `js/main.js` (1558 dòng) thành 7 modules độc lập.

### Dependency Graph
```
utils.js        ← Load đầu tiên (CSRF, fetch interceptor, helpers)
  ├── cart.js   ← Giỏ hàng, voucher, checkout
  ├── menu.js   ← Tải thực đơn
  ├── payment.js← QR modal, polling
  │   ├── booking.js  ← Đặt bàn (phụ thuộc payment + menu)
  │   └── profile.js  ← Trang cá nhân
  └── reviews.js← Đánh giá
```

### Load theo trang
| Trang          | Modules                                    |
|----------------|--------------------------------------------|
| `index.html`   | utils, cart, menu, reviews                 |
| `booking.html` | utils, cart, menu, payment, booking        |
| `profile.html` | utils, payment, profile, reviews           |

### Khi nào nên dùng ES Modules/Bundler?
Khi có **>10 JS files** hoặc cần **tree-shaking** → Cân nhắc **Vite** với native ES Modules.

---

## ADR-006: Local Verification Harness

**Date:** 2026-05  
**Status:** Active

### Decision
The project uses lightweight PowerShell smoke scripts instead of adding PHPUnit, npm test tooling, or a browser test framework.

### Rationale
- Keeps the zero-dependency PHP approach.
- Runs cleanly on Laragon/Windows.
- Covers high-risk flows: syntax, public APIs, auth boundaries, payment/booking validation, and admin observability.

### Commands
```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\payment_booking_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
```

### When to Revisit
If the app gains more complex business rules or more contributors, add PHPUnit for service-level tests and Playwright for browser workflows.

---

## ADR-007: Admin Observability

**Date:** 2026-05  
**Status:** Active

### Decision
Admins use `admin/logs.php` plus authenticated APIs to inspect sanitized log entries and system health.

### Rationale
- Direct access to `logs/` remains blocked by `.htaccess`.
- The UI gives enough operational context for demos and local incidents.
- Sensitive keys are redacted before log entries are returned to the browser.

### Health Checks
- Database connectivity.
- Log directory writability.
- Rate-limit directory writability.
- Migration table presence.
- PHP version.

---

## Hướng Phát Triển Dài Hạn

### Ngắn hạn (0-3 tháng)
- [x] Thêm `token_version` vào JWT payload để hỗ trợ revocation (`api/auth/login.php`)
- [x] Tích hợp Logger vào `api/auth/login.php` (user login)
- [x] Tích hợp Logger vào `api/payment/webhook.php` (mọi webhook event)
- [x] Chạy `php Database/migrate.php` trên local/Laragon để đảm bảo có `token_version` column

### Trung hạn (3-6 tháng)
- [x] Thêm admin API endpoint để xem logs (`/admin/logs`)
- [ ] Implement Email notification khi có CRITICAL log
- [ ] Rate limit cho tất cả public API endpoints (không chỉ login)
- [ ] Thêm payment_test.php vào test suite

### Dài hạn (6+ tháng)
- [ ] Đánh giá migrate sang **Slim Framework 4** nếu team scale
- [ ] Implement Redis cho rate limiting (thay file-based)
- [ ] CDN cho static assets (photos, CSS, JS)
- [ ] Thêm webhook retry mechanism với dead-letter queue

---

## Bảng Tóm Tắt Công Nghệ

| Layer         | Technology      | Version | Notes                          |
|---------------|-----------------|---------|--------------------------------|
| Backend       | PHP             | 8.3.x   | Native, no framework           |
| Database      | MySQL           | 8.4.x   | InnoDB, utf8mb4                |
| Web Server    | Apache          | 2.4.x   | mod_rewrite, mod_headers       |
| Auth (Web)    | PHP Session     | -       | HttpOnly, SameSite=Lax         |
| Auth (API)    | JWT HS256       | -       | Custom impl, no library        |
| Payment       | SePay           | -       | Webhook + QR polling           |
| Email         | Gmail SMTP/PHPMailer | -  | OTP, booking confirmation      |
| Frontend      | Vanilla JS      | ES2020+ | 7 modules, no bundler          |
| CSS           | Vanilla CSS     | -       | Component-based, no framework  |
| Logging       | Custom NDJSON   | -       | File-based, 5MB rotation       |
| Testing       | Custom Runner   | -       | No PHPUnit, CLI-based          |
