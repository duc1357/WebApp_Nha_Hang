<?php
// api/admin/update_voucher.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth_check_api.php'; // [2.3] Standardized
require_once ROOT_PATH . '/config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data['id'] ?? 0);
$code = strtoupper(trim($data['code'] ?? ''));
$discountType = $data['discount_type'] ?? 'percent';
$discountValue = floatval($data['discount_value'] ?? 0);
$minOrderValue = floatval($data['min_order_value'] ?? 0);
$usageLimit = intval($data['usage_limit'] ?? 100);
$expireDate = $data['expire_date'] ?? '';

if (!$id || !$code || !$discountValue || !$expireDate) {
    echo json_encode(['success' => false, 'message' => 'Thiáº¿u thÃ´ng tin báº¯t buá»™c']);
    exit;
}

$conn = getDbConnection();

// Check unique code (excluding current id)
$checkSql = "SELECT id FROM vouchers WHERE code = ? AND id != ?";
$stmt = $conn->prepare($checkSql);
$stmt->bind_param("si", $code, $id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'MÃ£ Voucher nÃ y Ä‘Ã£ tá»“n táº¡i']);
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
    echo json_encode(['success' => true, 'message' => 'Cáº­p nháº­t thÃ nh cÃ´ng']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lá»—i: ' . $conn->error]);
}

$conn->close();
?>

