<?php
// api/admin/check_new_orders.php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

// Query to count orders newer than last_id
// We also get the MAX(id) to return as the new latest_id
$sql = "SELECT COUNT(*) as new_count, MAX(id) as latest_id FROM orders WHERE id > ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $last_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$new_count = $row['new_count'] ? (int)$row['new_count'] : 0;
// If no new orders, latest_id might be null, so keep the old one or 0
$latest_id = $row['latest_id'] ? (int)$row['latest_id'] : $last_id;

echo json_encode([
    'success' => true,
    'new_count' => $new_count,
    'latest_id' => $latest_id
]);

$stmt->close();
$conn->close();
