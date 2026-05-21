<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) {
    ResponseService::json(['success' => false, 'message' => 'ID không hợp lệ'], 400);
    exit;
}

if ($id === (int)$_SESSION['user_id']) {
    ResponseService::json(['success' => false, 'message' => 'Không thể xóa chính tài khoản admin đang đăng nhập'], 409);
    exit;
}

$conn = getDbConnection();
$stmt = $conn->prepare('UPDATE users SET deleted_at = NOW() WHERE id = ?');
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    ResponseService::json(['success' => true, 'message' => 'Đã chuyển người dùng vào thùng rác']);
} else {
    error_log('[AdminDeleteUser] ' . $stmt->error);
    ResponseService::json(['success' => false, 'message' => 'Không thể xóa người dùng'], 500);
}

$stmt->close();
$conn->close();
