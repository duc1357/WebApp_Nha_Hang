<?php
// api/admin/update_voucher.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data['id'] ?? 0);
$code = strtoupper(trim($data['code'] ?? ''));
$discountType = $data['discount_type'] ?? 'percent';
$discountValue = floatval($data['discount_value'] ?? 0);
$minOrderValue = floatval($data['min_order_value'] ?? 0);
$usageLimit = intval($data['usage_limit'] ?? 100);
$expireDate = $data['expire_date'] ?? '';

if (!$id || !$code || !$discountValue || !$expireDate) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin bắt buộc']);
    exit;
}

$conn = getDbConnection();

// Check unique code (excluding current id)
$checkSql = "SELECT id FROM vouchers WHERE code = ? AND id != ?";
$stmt = $conn->prepare($checkSql);
$stmt->bind_param("si", $code, $id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Mã Voucher này đã tồn tại']);
    exit;
}

$sql = "UPDATE vouchers SET 
            code = ?, 
            discount_type = ?, 
            discount_value = ?, 
            min_order_value = ?, 
            usage_limit = ?, 
            expire_date = ? 
        WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssddisi", $code, $discountType, $discountValue, $minOrderValue, $usageLimit, $expireDate, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $conn->error]);
}

$conn->close();
?>
