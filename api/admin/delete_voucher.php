<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
// api/admin/delete_voucher.php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();

require_once ROOT_PATH . '/config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id'] ?? 0);

if ($id <= 0) {
    ResponseService::json(['success' => false, 'message' => 'ID không hợp lệ'], 400);
    exit;
}

$conn = getDbConnection();
$stmt = $conn->prepare('UPDATE vouchers SET is_active = 0 WHERE id = ?');
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    ResponseService::json(['success' => true, 'message' => 'Đã vô hiệu hóa mã giảm giá']);
} else {
    error_log('[AdminDeleteVoucher] ' . $stmt->error);
    ResponseService::json(['success' => false, 'message' => 'Không thể vô hiệu hóa mã giảm giá'], 500);
}

$stmt->close();
$conn->close();
