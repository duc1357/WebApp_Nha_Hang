<?php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = getDbConnection();
$stmt = $conn->prepare('UPDATE menu_items SET deleted_at = NOW() WHERE id = ?');
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Đã chuyển món vào thùng rác'], JSON_UNESCAPED_UNICODE);
} else {
    error_log('[AdminDeleteMenuItem] ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Không thể xóa món'], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
