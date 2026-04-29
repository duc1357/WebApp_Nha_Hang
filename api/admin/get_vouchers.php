<?php
// api/admin/get_vouchers.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth_check_api.php'; // [2.3] Standardized
require_once ROOT_PATH . '/config/db.php';

$conn = getDbConnection();
$sql = "SELECT * FROM vouchers ORDER BY created_at DESC";
$result = $conn->query($sql);

$vouchers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $vouchers[] = $row;
    }
}

echo json_encode(['success' => true, 'vouchers' => $vouchers]);
$conn->close();
?>

