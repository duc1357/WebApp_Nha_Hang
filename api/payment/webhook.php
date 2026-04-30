<?php
// CODE-07: Guard ob_clean() với ob_get_level()
if (ob_get_level()) ob_clean();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';

// [SECURITY FIX] Fail-Closed: Chặn tất cả request nếu token chưa cấu hình
if (empty(SEPAY_WEBHOOK_TOKEN)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Webhook not configured']);
    file_put_contents(dirname(__DIR__, 2) . '/logs/webhook_sepay.log', date('Y-m-d H:i:s') . " - CRITICAL: SEPAY_WEBHOOK_TOKEN is empty! Set it in .env\n", FILE_APPEND);
    exit;
}

// Kiểm tra Authorization header từ SePay
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (empty($authHeader) && function_exists('apache_request_headers')) {
    $reqHeaders = apache_request_headers();
    $authHeader = isset($reqHeaders['Authorization']) ? $reqHeaders['Authorization'] : '';
}

// [SECURITY FIX] Fail-Closed: Luôn kiểm tra token (không có exception)
if ($authHeader !== 'Apikey ' . SEPAY_WEBHOOK_TOKEN) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized Webhook!']);
    file_put_contents(dirname(__DIR__, 2) . '/logs/webhook_sepay.log', date('Y-m-d H:i:s') . " - UNAUTHORIZED ACCESS ATTEMPT | Auth: $authHeader\n", FILE_APPEND);
    exit;
}

// Đọc và log webhook data (chỉ log sau khi đã xác thực)
$logFile = dirname(__DIR__, 2) . '/logs/webhook_sepay.log';
$json = file_get_contents('php://input');
file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $json . PHP_EOL, FILE_APPEND);

$data = json_decode($json, true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// SePay Data Structure
// {
//   "gateway": "MBBank",
//   "transactionDate": "...",
//   "accountNumber": "...",
//   "subAccount": null,
//   "transferAmount": 50000,
//   "transferType": "in",
//   "content": "DH123",
//   ...
// }

$content = isset($data['content']) ? $data['content'] : '';
$amount  = isset($data['transferAmount']) ? (int)$data['transferAmount'] : 0;

// Extract Order ID or Booking ID from content
if (preg_match('/DH(\d+)/i', $content, $matches)) {
    $order_id = $matches[1];
    $conn = getDbConnection();

    $stmt = $conn->prepare("SELECT id, total_amount, final_total, status FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    if ($order) {
        $expectedAmount = isset($order['final_total']) ? (int)$order['final_total'] : (int)$order['total_amount'];
        if ($amount < $expectedAmount) {
             http_response_code(400); 
             echo json_encode(['success' => false, 'message' => 'Insufficient payment amount']);
             file_put_contents($logFile, date('Y-m-d H:i:s') . " - INSUFFICIENT: DH$order_id\n", FILE_APPEND);
             exit;
        }

        if ($order['status'] === 'paid') {
             http_response_code(200);
             echo json_encode(['success' => true, 'message' => 'Order already paid']);
             exit;
        }

        $updateStmt = $conn->prepare("UPDATE orders SET status = 'paid', payment_method = 'bank_transfer' WHERE id = ?");
        $updateStmt->bind_param("i", $order_id);
        
        if ($updateStmt->execute()) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Order updated']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Update failed']);
        }
        $updateStmt->close();
    } else {
        http_response_code(200); 
        echo json_encode(['success' => false, 'message' => 'Order not found']);
    }
    $stmt->close();
    $conn->close();

} elseif (preg_match('/BKG(\d+)/i', $content, $matches)) {
    // Xử lý cọc Đặt bàn
    $booking_id = $matches[1];
    $conn = getDbConnection();

    $stmt = $conn->prepare("SELECT id, deposit_amount, payment_status FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    if ($booking) {
        $expectedAmount = (int)$booking['deposit_amount'];
        
        if ($amount < $expectedAmount) {
             http_response_code(400);
             echo json_encode(['success' => false, 'message' => 'Insufficient deposit amount']);
             file_put_contents($logFile, date('Y-m-d H:i:s') . " - INSUFFICIENT DEPOSIT: BKG$booking_id\n", FILE_APPEND);
             exit;
        }

        if ($booking['payment_status'] === 'partial' || $booking['payment_status'] === 'paid') {
             http_response_code(200);
             echo json_encode(['success' => true, 'message' => 'Booking deposit already paid']);
             exit;
        }

        $updateStmt = $conn->prepare("UPDATE bookings SET payment_status = 'partial', status = 'confirmed' WHERE id = ?");
        $updateStmt->bind_param("i", $booking_id);
        
        if ($updateStmt->execute()) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Booking updated']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
        }
        $updateStmt->close();
    } else {
        http_response_code(200); 
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
    }
    $stmt->close();
    $conn->close();

} else {
    echo json_encode(['success' => false, 'message' => 'No matching Code found in content']);
}
