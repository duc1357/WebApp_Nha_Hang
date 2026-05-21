<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();
require_once ROOT_PATH . '/config/db.php';

$conn = getDbConnection();
$stmt = $conn->prepare('UPDATE vouchers SET is_active = 0 WHERE expire_date < NOW() AND is_active = 1');

if ($stmt->execute()) {
    ResponseService::json(['success' => true, 'message' => 'Đã vô hiệu hóa ' . $stmt->affected_rows . ' mã hết hạn.']);
} else {
    error_log('[AdminDeleteExpiredVouchers] ' . $stmt->error);
    ResponseService::json(['success' => false, 'message' => 'Không thể xử lý mã hết hạn'], 500);
}

$stmt->close();
$conn->close();
