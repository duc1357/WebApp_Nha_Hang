<?php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? null;
$status = $data['status'] ?? null;

if (!$id || !$status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu tham số'], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowed_statuses = ['pending', 'confirmed', 'cancelled', 'completed'];
if (!in_array($status, $allowed_statuses, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = getDbConnection();

// Update status
$sql = "UPDATE bookings SET status = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thành công'], JSON_UNESCAPED_UNICODE);
} else {
    error_log('[AdminUpdateBookingStatus] ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Không thể cập nhật trạng thái'], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
