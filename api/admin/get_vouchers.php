<?php
// api/admin/get_vouchers.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

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
