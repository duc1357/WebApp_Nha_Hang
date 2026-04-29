<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDbConnection();

// Delete vouchers where expire_date < NOW()
$sql = "DELETE FROM vouchers WHERE expire_date < NOW()";
if ($conn->query($sql)) {
    $deletedCount = $conn->affected_rows;
    echo json_encode(['success' => true, 'message' => "Đã xóa $deletedCount mã hết hạn."]);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $conn->error]);
}

$conn->close();
?>
