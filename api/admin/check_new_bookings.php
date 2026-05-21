<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
// api/admin/check_new_bookings.php
require_once __DIR__ . '/auth_check_api.php';

require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

// Get latest ID and count of new bookings
$sql = "SELECT COUNT(*) as new_count, MAX(id) as latest_id FROM bookings WHERE id > ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lastId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$new_count = $row['new_count'];
$latest_id = $row['latest_id'] ? $row['latest_id'] : $lastId;

ResponseService::json([
    'success' => true,
    'new_count' => $new_count,
    'latest_id' => $latest_id
]);

$conn->close();
?>
