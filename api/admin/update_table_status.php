<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
// api/admin/update_table_status.php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();

require_once __DIR__ . '/../../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;
$status = isset($data['status']) ? trim($data['status']) : '';

if ($id <= 0 || !in_array($status, ['available', 'occupied'], true)) {
    ResponseService::json(['success' => false, 'message' => 'Dữ liệu không hợp lệ'], 422);
    exit;
}

$conn = getDbConnection();
$stmt = $conn->prepare('UPDATE tables SET status = ? WHERE id = ?');
$stmt->bind_param('si', $status, $id);

if ($stmt->execute()) {
    ResponseService::json(['success' => true, 'message' => 'Cập nhật trạng thái thành công']);
} else {
    error_log('[AdminUpdateTableStatus] ' . $stmt->error);
    ResponseService::json(['success' => false, 'message' => 'Không thể cập nhật trạng thái'], 500);
}

$stmt->close();
$conn->close();
