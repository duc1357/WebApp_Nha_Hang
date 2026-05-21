<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();

if (ob_get_level()) ob_clean();
header('X-Content-Type-Options: nosniff');

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/OrderService.php';
require_once ROOT_PATH . '/api/services/payment_state_service.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    ResponseService::json(['success' => false, 'message' => 'JSON khong hop le'], 400);
    exit;
}

$id = (int)($data['id'] ?? 0);
$status = trim((string)($data['status'] ?? ''));
$allowed = PaymentStateService::orderStatuses();

if ($id <= 0 || !in_array($status, $allowed, true)) {
    ResponseService::json(['success' => false, 'message' => 'ID hoac trang thai khong hop le'], 422);
    exit;
}

$conn = getDbConnection();
$inTransaction = false;

try {
    if ($status === 'paid') {
        $conn->begin_transaction();
        $inTransaction = true;
        $result = OrderService::markOrderPaid($conn, $id, 'cash');

        if (!$result['success']) {
            $conn->rollback();
            $inTransaction = false;
            ResponseService::json(['success' => false, 'message' => $result['message']], $result['message'] === 'Order not found' ? 404 : 409);
            $conn->close();
            exit;
        }

        $conn->commit();
        $inTransaction = false;
        ResponseService::json([
            'success' => true,
            'message' => 'Cap nhat trang thai thanh cong',
            'id' => $id,
            'status' => $status,
        ]);
        $conn->close();
        exit;
    }

    $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
    if (!$stmt) {
        throw new RuntimeException('Prepare order status update failed: ' . $conn->error);
    }

    $stmt->bind_param('si', $status, $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        ResponseService::json([
            'success' => true,
            'message' => 'Cap nhat trang thai thanh cong',
            'id' => $id,
            'status' => $status,
        ]);
    } else {
        ResponseService::json(['success' => false, 'message' => 'Khong tim thay don de cap nhat'], 404);
    }

    $stmt->close();
} catch (Throwable $e) {
    if ($inTransaction) {
        $conn->rollback();
    }
    error_log('[AdminUpdateOrderStatus] ' . $e->getMessage());
    ResponseService::json(['success' => false, 'message' => 'Khong the cap nhat trang thai don hang'], 500);
}

$conn->close();
