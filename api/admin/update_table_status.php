<?php
// api/admin/update_table_status.php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;
$status = isset($data['status']) ? trim($data['status']) : '';

if ($id <= 0 || !in_array($status, ['available', 'occupied'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = getDbConnection();
$stmt = $conn->prepare('UPDATE tables SET status = ? WHERE id = ?');
$stmt->bind_param('si', $status, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thành công'], JSON_UNESCAPED_UNICODE);
} else {
    error_log('[AdminUpdateTableStatus] ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Không thể cập nhật trạng thái'], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
