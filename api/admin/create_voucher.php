<?php
// api/admin/create_voucher.php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($data['code'] ?? ''));
$discountType = trim($data['discount_type'] ?? 'percent');
$discountValue = (float)($data['discount_value'] ?? 0);
$minOrderValue = (float)($data['min_order_value'] ?? 0);
$usageLimit = (int)($data['usage_limit'] ?? 100);
$expireDate = trim($data['expire_date'] ?? '');

if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $code) || !in_array($discountType, ['percent', 'fixed'], true) || $discountValue <= 0 || $usageLimit <= 0 || !$expireDate) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Thông tin mã giảm giá không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($discountType === 'percent' && $discountValue > 100) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Phần trăm giảm giá không được vượt quá 100%'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = getDbConnection();

$stmt = $conn->prepare('SELECT id FROM vouchers WHERE code = ?');
$stmt->bind_param('s', $code);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Mã giảm giá này đã tồn tại'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt->close();

$stmt = $conn->prepare('INSERT INTO vouchers (code, discount_type, discount_value, min_order_value, usage_limit, expire_date) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->bind_param('ssddis', $code, $discountType, $discountValue, $minOrderValue, $usageLimit, $expireDate);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Tạo mã giảm giá thành công'], JSON_UNESCAPED_UNICODE);
} else {
    error_log('[AdminCreateVoucher] ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Không thể tạo mã giảm giá'], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
