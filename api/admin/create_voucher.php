<?php
// api/admin/create_voucher.php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

$code = strtoupper(trim($data['code'] ?? ''));
$discountType = $data['discount_type'] ?? 'percent'; // percent or fixed
$discountValue = floatval($data['discount_value'] ?? 0);
$minOrderValue = floatval($data['min_order_value'] ?? 0);
$usageLimit = intval($data['usage_limit'] ?? 100);
$expireDate = $data['expire_date'] ?? '';

if (!$code || !$discountValue || !$expireDate) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin']);
    exit;
}

$conn = getDbConnection();

// Check if code exists
$checkSql = "SELECT id FROM vouchers WHERE code = ?";
$stmt = $conn->prepare($checkSql);
$stmt->bind_param("s", $code);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Mã giảm giá này đã tồn tại']);
    exit;
}

$sql = "INSERT INTO vouchers (code, discount_type, discount_value, min_order_value, usage_limit, expire_date) 
        VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssddis", $code, $discountType, $discountValue, $minOrderValue, $usageLimit, $expireDate);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Tạo mã giảm giá thành công']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $conn->error]);
}

$conn->close();
?>
