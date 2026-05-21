<?php
// api/public/check_voucher.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/response_service.php';

$data = json_decode(file_get_contents("php://input"), true);
$code = strtoupper(trim($data['code'] ?? ''));
$orderValue = floatval($data['order_value'] ?? 0);

if (!$code) {
    ResponseService::error('Vui lòng nhập mã');
}

$conn = getDbConnection();
$sql = "SELECT code, discount_type, discount_value, min_order_value, expire_date, usage_limit, used_count
        FROM vouchers
        WHERE code = ? AND is_active = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    ResponseService::error('Mã giảm giá không tồn tại hoặc đã bị khóa', 404);
}

$voucher = $result->fetch_assoc();
$now = new DateTime();
$expire = new DateTime($voucher['expire_date']);

// 1. Check expiry
if ($now > $expire) {
    ResponseService::error('Mã giảm giá đã hết hạn', 410);
}

// 2. Check usage limit
if ($voucher['used_count'] >= $voucher['usage_limit']) {
    ResponseService::error('Mã giảm giá đã hết lượt sử dụng', 409);
}

// 3. Check min order value
if ($orderValue < $voucher['min_order_value']) {
    ResponseService::error('Đơn hàng chưa đủ điều kiện áp dụng mã này (Tối thiểu ' . number_format($voucher['min_order_value']) . 'đ)', 422);
}

// Calculate discount
$discountAmount = 0;
if ($voucher['discount_type'] === 'percent') {
    $discountAmount = ($orderValue * $voucher['discount_value']) / 100;
} else {
    $discountAmount = $voucher['discount_value'];
}

// Ensure discount doesn't exceed order value (logic check)
if ($discountAmount > $orderValue) {
    $discountAmount = $orderValue;
}

ResponseService::success([
    'message' => 'Áp dụng mã thành công',
    'voucher' => [
        'code' => $voucher['code'],
        'discount_amount' => $discountAmount,
        'discount_type' => $voucher['discount_type'],
        'discount_value' => $voucher['discount_value']
    ]
]);
