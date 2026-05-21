<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/payment_state_service.php';

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? null;
$status = $data['status'] ?? null;

if (!$id || !$status) {
    ResponseService::json(['success' => false, 'message' => 'Thiếu tham số'], 400);
    exit;
}

$allowed_statuses = PaymentStateService::bookingStatuses();
if (!in_array($status, $allowed_statuses, true)) {
    ResponseService::json(['success' => false, 'message' => 'Trạng thái không hợp lệ'], 422);
    exit;
}

$conn = getDbConnection();

// Update status
$sql = "UPDATE bookings SET status = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    ResponseService::json(['success' => true, 'message' => 'Cập nhật trạng thái thành công']);
} else {
    error_log('[AdminUpdateBookingStatus] ' . $stmt->error);
    ResponseService::json(['success' => false, 'message' => 'Không thể cập nhật trạng thái'], 500);
}

$stmt->close();
$conn->close();
