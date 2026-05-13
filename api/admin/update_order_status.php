<?php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();

if (ob_get_level()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/OrderService.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON khong hop le'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int)($data['id'] ?? 0);
$status = trim((string)($data['status'] ?? ''));
$allowed = ['pending', 'paid', 'cancelled'];

if ($id <= 0 || !in_array($status, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'ID hoac trang thai khong hop le'], JSON_UNESCAPED_UNICODE);
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
            http_response_code($result['message'] === 'Order not found' ? 404 : 409);
            echo json_encode(['success' => false, 'message' => $result['message']], JSON_UNESCAPED_UNICODE);
            $conn->close();
            exit;
        }

        $conn->commit();
        $inTransaction = false;
        echo json_encode([
            'success' => true,
            'message' => 'Cap nhat trang thai thanh cong',
            'id' => $id,
            'status' => $status,
        ], JSON_UNESCAPED_UNICODE);
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
        echo json_encode([
            'success' => true,
            'message' => 'Cap nhat trang thai thanh cong',
            'id' => $id,
            'status' => $status,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Khong tim thay don de cap nhat'], JSON_UNESCAPED_UNICODE);
    }

    $stmt->close();
} catch (Throwable $e) {
    if ($inTransaction) {
        $conn->rollback();
    }
    error_log('[AdminUpdateOrderStatus] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Khong the cap nhat trang thai don hang'], JSON_UNESCAPED_UNICODE);
}

$conn->close();
