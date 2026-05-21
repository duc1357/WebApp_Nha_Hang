<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
// api/admin/get_vouchers.php
require_once __DIR__ . '/auth_check_api.php'; // [2.3] Standardized
require_once ROOT_PATH . '/config/db.php';

$conn = getDbConnection();
$sql = "SELECT id, code, description, discount_type, discount_value, min_order_value, expire_date, usage_limit, used_count, is_active, created_at
        FROM vouchers
        ORDER BY created_at DESC";
$result = $conn->query($sql);

$vouchers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $vouchers[] = $row;
    }
}

ResponseService::json(['success' => true, 'vouchers' => $vouchers]);
$conn->close();
?>
