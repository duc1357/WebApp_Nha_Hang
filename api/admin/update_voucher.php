<?php
// api/admin/update_voucher.php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();
header('Content-Type: application/json; charset=utf-8');

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

$stmt = $conn->prepare('SELECT id FROM vouchers WHERE code = ? AND id != ?');
$stmt->bind_param('si', $code, $id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Mã voucher này đã tồn tại'], JSON_UNESCAPED_UNICODE);
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
    echo json_encode(['success' => true, 'message' => 'Cập nhật thành công'], JSON_UNESCAPED_UNICODE);
} else {
    error_log('[AdminUpdateVoucher] ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Không thể cập nhật mã giảm giá'], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
