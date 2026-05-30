<?php
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/payment_state_service.php';
require_once ROOT_PATH . '/api/services/auth_state_service.php';

$authUser = AuthStateService::requireSession();
$userId = (int)$authUser['id'];
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId <= 0) {
    ResponseService::error('Missing order ID', 422);
}

$conn = getDbConnection();
$stmt = $conn->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $status = (string)$row['status'];
    if (!in_array($status, PaymentStateService::orderStatuses(), true)) {
        $stmt->close();
        $conn->close();
        ResponseService::error('Invalid order status', 500);
    }

    $stmt->close();
    $conn->close();
    ResponseService::success([
        'status' => $status,
        'is_paid' => PaymentStateService::isPaidOrderStatus($status),
    ]);
}

$stmt->close();
$conn->close();
ResponseService::error('Order not found', 404);
