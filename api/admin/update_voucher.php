<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
// api/admin/update_voucher.php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();

require_once ROOT_PATH . '/config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id'] ?? 0);
$code = strtoupper(trim($data['code'] ?? ''));
$discountType = trim($data['discount_type'] ?? 'percent');
$discountValue = (float)($data['discount_value'] ?? 0);
$minOrderValue = (float)($data['min_order_value'] ?? 0);
$usageLimit = (int)($data['usage_limit'] ?? 100);
$expireDate = trim($data['expire_date'] ?? '');

if ($id <= 0 || !preg_match('/^[A-Z0-9_-]{3,30}$/', $code) || !in_array($discountType, ['percent', 'fixed'], true) || $discountValue <= 0 || $usageLimit <= 0 || !$expireDate) {
    ResponseService::json(['success' => false, 'message' => 'Thông tin mã giảm giá không hợp lệ'], 422);
    exit;
}
if ($discountType === 'percent' && $discountValue > 100) {
    ResponseService::json(['success' => false, 'message' => 'Phần trăm giảm giá không được vượt quá 100%'], 422);
    exit;
}

$conn = getDbConnection();

$stmt = $conn->prepare('SELECT id FROM vouchers WHERE code = ? AND id != ?');
$stmt->bind_param('si', $code, $id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    ResponseService::json(['success' => false, 'message' => 'Mã voucher này đã tồn tại'], 409);
    exit;
}
$stmt->close();

$stmt = $conn->prepare('
    UPDATE vouchers
    SET code = ?, discount_type = ?, discount_value = ?, min_order_value = ?, usage_limit = ?, expire_date = ?
    WHERE id = ?
');
$stmt->bind_param('ssddisi', $code, $discountType, $discountValue, $minOrderValue, $usageLimit, $expireDate, $id);

if ($stmt->execute()) {
    ResponseService::json(['success' => true, 'message' => 'Cập nhật thành công']);
} else {
    error_log('[AdminUpdateVoucher] ' . $stmt->error);
    ResponseService::json(['success' => false, 'message' => 'Không thể cập nhật mã giảm giá'], 500);
}

$stmt->close();
$conn->close();
