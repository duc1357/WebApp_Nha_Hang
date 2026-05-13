<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();
require_once ROOT_PATH . '/config/db.php';

$conn = getDbConnection();
$stmt = $conn->prepare('UPDATE vouchers SET is_active = 0 WHERE expire_date < NOW() AND is_active = 1');

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Đã vô hiệu hóa ' . $stmt->affected_rows . ' mã hết hạn.'], JSON_UNESCAPED_UNICODE);
} else {
    error_log('[AdminDeleteExpiredVouchers] ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Không thể xử lý mã hết hạn'], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
