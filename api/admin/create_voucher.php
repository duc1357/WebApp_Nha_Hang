<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
// api/admin/create_voucher.php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();

require_once __DIR__ . '/../../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($data['code'] ?? ''));
$discountType = trim($data['discount_type'] ?? 'percent');
$discountValue = (float)($data['discount_value'] ?? 0);
$minOrderValue = (float)($data['min_order_value'] ?? 0);
$usageLimit = (int)($data['usage_limit'] ?? 100);
$expireDate = trim($data['expire_date'] ?? '');

if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $code) || !in_array($discountType, ['percent', 'fixed'], true) || $discountValue <= 0 || $usageLimit <= 0 || !$expireDate) {
    ResponseService::json(['success' => false, 'message' => 'Thông tin mã giảm giá không hợp lệ'], 422);
    exit;
}
if ($discountType === 'percent' && $discountValue > 100) {
    ResponseService::json(['success' => false, 'message' => 'Phần trăm giảm giá không được vượt quá 100%'], 422);
    exit;
}

$conn = getDbConnection();

$stmt = $conn->prepare('SELECT id FROM vouchers WHERE code = ?');
$stmt->bind_param('s', $code);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    ResponseService::json(['success' => false, 'message' => 'Mã giảm giá này đã tồn tại'], 409);
    exit;
}
$stmt->close();

$stmt = $conn->prepare('INSERT INTO vouchers (code, discount_type, discount_value, min_order_value, usage_limit, expire_date) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->bind_param('ssddis', $code, $discountType, $discountValue, $minOrderValue, $usageLimit, $expireDate);

if ($stmt->execute()) {
    ResponseService::json(['success' => true, 'message' => 'Tạo mã giảm giá thành công']);
} else {
    error_log('[AdminCreateVoucher] ' . $stmt->error);
    ResponseService::json(['success' => false, 'message' => 'Không thể tạo mã giảm giá'], 500);
}

$stmt->close();
$conn->close();
