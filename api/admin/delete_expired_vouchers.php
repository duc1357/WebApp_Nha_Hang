<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth_check_api.php'; // [2.3] Standardized
require_once ROOT_PATH . '/config/db.php';

$conn = getDbConnection();

// Delete vouchers where expire_date < NOW()
$sql = "DELETE FROM vouchers WHERE expire_date < NOW()";
if ($conn->query($sql)) {
    $deletedCount = $conn->affected_rows;
    echo json_encode(['success' => true, 'message' => "ÄÃ£ xÃ³a $deletedCount mÃ£ háº¿t háº¡n."]);
} else {
    echo json_encode(['success' => false, 'message' => 'Lá»—i: ' . $conn->error]);
}

$conn->close();
?>

