<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__, 2) . '/logs/payment_error.log');

if (ob_get_level()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once __DIR__ . '/../../api/services/csrf_service.php';
require_once __DIR__ . '/../../api/services/OrderService.php';
require_once __DIR__ . '/../../api/services/rate_limit_service.php';

CsrfService::validateRequest();

$conn = getDbConnection();
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['items']) || !isset($data['payment_method'])) {
    echo json_encode(['success' => false, 'message' => 'Du lieu khong hop le']);
    exit;
}

$items          = $data['items'];
$payment_method = trim((string)$data['payment_method']);
$address        = isset($data['address']) ? trim((string)$data['address']) : null;
$table_input    = isset($data['table_id']) ? trim((string)$data['table_id']) : null;
$note           = isset($data['note']) ? trim((string)$data['note']) : null;
$voucher_code   = isset($data['voucher_code']) ? trim((string)$data['voucher_code']) : null;
$user_id        = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Ban can dang nhap de dat hang.']);
    exit;
}

if (!in_array($payment_method, ['cash', 'bank_transfer'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Phuong thuc thanh toan khong hop le.']);
    exit;
}

if (!RateLimitService::check('create_payment_' . $user_id, 10, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Thao tac qua nhanh. Vui long doi 1 phut.']);
    exit;
}

$table_id = OrderService::getTableId($conn, $table_input);
if ($table_input !== null && $table_input !== '' && $table_id === null) {
    echo json_encode(['success' => false, 'message' => 'Ten ban khong hop le hoac khong ton tai.']);
    exit;
}

$calcResult       = OrderService::calculateOrderTotal($conn, $items);
$total_calculated = $calcResult['total_calculated'];
$valid_items      = $calcResult['valid_items'];

if (empty($valid_items) || $total_calculated <= 0) {
    echo json_encode(['success' => false, 'message' => 'Gio hang trong hoac mon khong hop le.']);
    exit;
}

$voucherResult   = OrderService::validateAndApplyVoucher($conn, $voucher_code, $total_calculated);
$discount_amount = $voucherResult['discount_amount'];
$final_total     = $voucherResult['final_total'];
$applied_voucher = $voucherResult['applied_voucher'];

$conn->begin_transaction();
try {
    $sql = "INSERT INTO orders (user_id, total_amount, discount_amount, final_total, payment_method, status, address, table_id, note, voucher_code, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare order failed: ' . $conn->error);
    }
    $stmt->bind_param("idddssiss", $user_id, $total_calculated, $discount_amount, $final_total, $payment_method, $address, $table_id, $note, $applied_voucher);
    if (!$stmt->execute()) {
        throw new RuntimeException('Order insert failed: ' . $stmt->error);
    }
    $order_id = (int)$stmt->insert_id;
    $stmt->close();

    $stmtItem = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
    if (!$stmtItem) {
        throw new RuntimeException('Prepare order item failed: ' . $conn->error);
    }
    foreach ($valid_items as $vItem) {
        $stmtItem->bind_param("iiii", $order_id, $vItem['menu_item_id'], $vItem['quantity'], $vItem['unit_price']);
        if (!$stmtItem->execute()) {
            throw new RuntimeException('Order item insert failed: ' . $stmtItem->error);
        }
    }
    $stmtItem->close();
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[Payment] Transaction failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Loi tao don hang. Vui long thu lai.']);
    $conn->close();
    exit;
}

require_once __DIR__ . '/../../api/services/email_service.php';
$emailStmt = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
$emailStmt->bind_param("i", $user_id);
$emailStmt->execute();
$emailRes = $emailStmt->get_result();

if ($uRow = $emailRes->fetch_assoc()) {
    $uEmail = $uRow['email'];
    $uName = htmlspecialchars($uRow['name'] ?? '', ENT_QUOTES, 'UTF-8');
    if ($uEmail) {
        $subject = "[Duong Bau] Xac nhan don hang #$order_id";
        $itemListHtml = "<ul>";
        foreach ($valid_items as $itm) {
            $iQty = (int)$itm['quantity'];
            $iPrice = number_format((int)$itm['unit_price']);
            $itemListHtml .= "<li>Mon #" . (int)$itm['menu_item_id'] . " x $iQty ($iPrice d)</li>";
        }
        $itemListHtml .= "</ul>";

        $payStr = ($payment_method === 'bank_transfer') ? 'Chuyen khoan (QR)' : 'Tien mat';
        $totalStr = number_format($final_total);
        $discountStr = ($discount_amount > 0) ? "<p>Giam gia: -" . number_format($discount_amount) . " d</p>" : "";

        $body = "<h2>Cam on ban da dat mon!</h2>
                 <p>Xin chao <strong>$uName</strong>,</p>
                 <p>Don hang <strong>#$order_id</strong> cua ban da duoc ghi nhan.</p>
                 <h3>Chi tiet don hang:</h3>
                 $itemListHtml
                 <hr>
                 $discountStr
                 <p><strong>Tong cong: $totalStr d</strong></p>
                 <p>Phuong thuc: $payStr</p>";

        EmailService::send($uEmail, $subject, $body);
    }
}
$emailStmt->close();

$response = [
    'success' => true,
    'order_id' => $order_id,
    'final_total' => $final_total,
];

if ($payment_method === 'bank_transfer') {
    $payment_content = "DH" . $order_id;
    $qrUrl = "https://qr.sepay.vn/img"
        . "?acc=" . urlencode(defined('SEPAY_VA_ACCOUNT') ? SEPAY_VA_ACCOUNT : '')
        . "&bank=" . urlencode(defined('SEPAY_BANK_NAME') ? SEPAY_BANK_NAME : 'MBBank')
        . "&amount=" . urlencode($final_total)
        . "&des=" . urlencode($payment_content);

    $response['payUrl'] = $qrUrl;
    $response['message'] = 'Vui long quet ma QR de thanh toan.';
} else {
    $response['message'] = 'Dat don thanh cong. Thanh toan khi nhan mon.';
}

$conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
