<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
// api/admin/checkout_table.php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../api/services/OrderService.php';

$data = json_decode(file_get_contents('php://input'), true);
$table_id = isset($data['table_id']) ? (int)$data['table_id'] : 0;
$payment_method = isset($data['payment_method']) ? trim($data['payment_method']) : 'cash';
$date = isset($data['date']) ? $data['date'] : date('Y-m-d');

if ($table_id <= 0 || !in_array($payment_method, ['cash', 'bank_transfer'], true)) {
    ResponseService::json(['success' => false, 'message' => 'Dữ liệu thanh toán không hợp lệ'], 422);
    exit;
}

$conn = getDbConnection();
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("SELECT id FROM orders WHERE table_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $table_id);
    $stmt->execute();
    $resOrder = $stmt->get_result();

    if ($resOrder->num_rows > 0) {
        $order = $resOrder->fetch_assoc();
        $order_id = (int)$order['id'];
        $markResult = OrderService::markOrderPaid($conn, $order_id, $payment_method);
        if (!$markResult['success']) {
            throw new RuntimeException($markResult['message']);
        }
    }
    $stmt->close();

    $upTable = $conn->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
    $upTable->bind_param('i', $table_id);
    $upTable->execute();
    $upTable->close();

    $completeB = $conn->prepare("UPDATE bookings SET status = 'completed' WHERE table_id = ? AND date = ? AND status = 'confirmed'");
    $completeB->bind_param('is', $table_id, $date);
    $completeB->execute();
    $completeB->close();

    $conn->commit();
    ResponseService::json(['success' => true, 'message' => 'Thanh toán và trả bàn thành công']);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[AdminCheckoutTable] ' . $e->getMessage());
    ResponseService::json(['success' => false, 'message' => 'Không thể thanh toán bàn'], 500);
}

$conn->close();
