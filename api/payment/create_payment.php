<?php
// Tắt hiển thị lỗi ngay từ đầu để tránh các Notice làm hỏng session_start()
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__, 2) . '/logs/payment_error.log');

// CODE-07: Guard ob_clean() với ob_get_level()
if (ob_get_level()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once __DIR__ . '/../../api/services/csrf_service.php';
// Yêu cầu class OrderService mới tạo
require_once __DIR__ . '/../../api/services/OrderService.php';

// Validate CSRF
CsrfService::validateRequest();

$conn = getDbConnection();

$data = json_decode(file_get_contents('php://input'), true);

// Không còn yêu cầu 'total' từ client vì ta sẽ tự tính toán
if (!$data || !isset($data['items']) || !isset($data['payment_method'])) {
    echo json_encode(['success' => false, 'message' => '❌ Dữ liệu không hợp lệ']);
    exit;
}

$items          = $data['items'];
$payment_method = $data['payment_method'];
$address        = isset($data['address']) ? trim($data['address']) : null;
$table_input    = isset($data['table_id']) ? trim($data['table_id']) : null;
$note           = isset($data['note']) ? trim($data['note']) : null;
$voucher_code   = isset($data['voucher_code']) ? trim($data['voucher_code']) : null;

// Lấy User ID từ session
$user_id = $_SESSION['user_id'] ?? null; 

// 1. Tìm chính xác Table ID
$table_id = OrderService::getTableId($conn, $table_input);
if ($table_input !== null && $table_input !== '' && $table_id === null) {
    echo json_encode(['success' => false, 'message' => '❌ Tên bàn không hợp lệ hoặc không tồn tại.']);
    exit;
}

// 2. Tính toán tổng tiền ở Backend & lấy danh sách món hợp lệ
$calcResult = OrderService::calculateOrderTotal($conn, $items);
$total_calculated = $calcResult['total_calculated'];
$valid_items      = $calcResult['valid_items'];

if (empty($valid_items) || $total_calculated <= 0) {
    echo json_encode(['success' => false, 'message' => '❌ Giỏ hàng trống hoặc món không hợp lệ.']);
    exit;
}

// 3. Áp dụng Voucher (nếu có)
$voucherResult   = OrderService::validateAndApplyVoucher($conn, $voucher_code, $total_calculated);
$discount_amount = $voucherResult['discount_amount'];
$final_total     = $voucherResult['final_total'];
$applied_voucher = $voucherResult['applied_voucher'];

// 4. Tạo đơn hàng (Chỉ thực hiện 1 câu lệnh INSERT chứa toàn bộ dữ liệu chính xác)
$sql = "INSERT INTO orders (user_id, total_amount, discount_amount, final_total, payment_method, status, address, table_id, note, voucher_code, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('[Payment] Prepare failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại.']);
    exit;
}

// i = integer, d = double, s = string
// $user_id (i), $total_calculated (d), $discount_amount (d), $final_total (d), $payment_method (s), $address (s), $table_id (i/s), $note (s), $applied_voucher (s)
$stmt->bind_param("idddssiss", $user_id, $total_calculated, $discount_amount, $final_total, $payment_method, $address, $table_id, $note, $applied_voucher);

if (!$stmt->execute()) {
    error_log('[Payment] Order insert failed: ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi tạo đơn hàng. Vui lòng thử lại.']);
    exit;
}
$order_id = $stmt->insert_id;
$stmt->close();

// 5. Lưu chi tiết Order Items
$sql_item = "INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price) VALUES (?, ?, ?, ?)";
$stmt_item = $conn->prepare($sql_item);
if ($stmt_item) {
    foreach ($valid_items as $vItem) {
        $stmt_item->bind_param("iiii", $order_id, $vItem['menu_item_id'], $vItem['quantity'], $vItem['unit_price']);
        if (!$stmt_item->execute()) {
            error_log("Failed to insert item " . $vItem['menu_item_id'] . " for order $order_id: " . $stmt_item->error);
        }
    }
    $stmt_item->close();
}

// 6. Gửi Email thông báo
require_once __DIR__ . '/../../api/services/email_service.php';
if ($user_id) {
    $emailStmt = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
    $emailStmt->bind_param("i", $user_id);
    $emailStmt->execute();
    $emailRes = $emailStmt->get_result();

    if ($uRow = $emailRes->fetch_assoc()) {
        $uEmail = $uRow['email'];
        $uName = $uRow['name'];
        if ($uEmail) {
            $subject = "[Dượng Bầu] Xác nhận đơn hàng #$order_id";
            
            // Xây dựng danh sách món từ request gốc để lấy tên
            $itemListHtml = "<ul>";
            foreach ($items as $itm) {
                $iName = htmlspecialchars($itm['name'] ?? 'Món ăn');
                $iQty = (int)($itm['quantity'] ?? 0);
                $iPrice = number_format((int)($itm['price'] ?? 0));
                if ($iQty > 0) {
                    $itemListHtml .= "<li>$iName x $iQty ($iPrice đ)</li>";
                }
            }
            $itemListHtml .= "</ul>";
            
            $payStr = ($payment_method === 'bank_transfer') ? 'Chuyển khoản (QR)' : 'Tiền mặt';
            $totalStr = number_format($final_total);
            $discountStr = ($discount_amount > 0) ? "<p>Giảm giá: -" . number_format($discount_amount) . " đ</p>" : "";

            $body = "<h2>Cảm ơn bạn đã đặt món!</h2>
                     <p>Xin chào <strong>$uName</strong>,</p>
                     <p>Đơn hàng <strong>#$order_id</strong> của bạn đã được ghi nhận.</p>
                     <h3>Chi tiết đơn hàng:</h3>
                     $itemListHtml
                     <hr>
                     $discountStr
                     <p><strong>Tổng cộng: $totalStr đ</strong></p>
                     <p>Phương thức: $payStr</p>
                     <p>Chúng tôi sẽ sớm giao món/phục vụ bạn!</p>";
                     
            EmailService::send($uEmail, $subject, $body);
        }
    }
    $emailStmt->close();
}

// 7. Phản hồi cho Frontend
$response = [
    'success'  => true,
    'order_id' => $order_id
];

if ($payment_method === 'bank_transfer') {
    $sepay_va_account_id = defined('SEPAY_VA_ACCOUNT') ? SEPAY_VA_ACCOUNT : '';
    $sepay_bank_name     = defined('SEPAY_BANK_NAME')  ? SEPAY_BANK_NAME  : 'MBBank';

    $payment_content = "DH" . $order_id;
    $amount          = $final_total;

    $qrUrl = "https://qr.sepay.vn/img"
           . "?acc=" . urlencode($sepay_va_account_id)
           . "&bank=" . urlencode($sepay_bank_name)
           . "&amount=" . urlencode($amount)
           . "&des=" . urlencode($payment_content);

    $response['payUrl']  = $qrUrl;
    $response['message'] = "Vui lòng quét mã QR để thanh toán.";
} else {
    $response['message'] = "Đặt đơn thành công. Thanh toán khi nhận món.";
}

$conn->close();
echo json_encode($response);