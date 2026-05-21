<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
// api/bookings/get_busy_tables.php
require_once __DIR__ . '/../../config/db.php';

$conn = getDbConnection();
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
// Optional: Time logic (e.g. busy +/- 2 hours). For now, busy all day if confirmed.

$busy_tables = [];

// Get confirmed bookings
$sql = "SELECT DISTINCT table_id FROM bookings WHERE date = ? AND status = 'confirmed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $busy_tables[] = $row['table_id'];
}

// Get Active Dine-In Orders (Tables currently eating)
// Logic: Orders created today that are still 'pending' (Khách chưa thanh toán)
$sqlO = "SELECT DISTINCT table_id FROM orders
         WHERE DATE(created_at) = ? AND table_id IS NOT NULL AND table_id != '' AND status = 'pending'";
$stmtO = $conn->prepare($sqlO);
$stmtO->bind_param("s", $date);
$stmtO->execute();
$resO = $stmtO->get_result();
while ($row = $resO->fetch_assoc()) {
    $tid = $row['table_id'];
    if (!in_array($tid, $busy_tables)) {
        $busy_tables[] = $tid;
    }
}

ResponseService::json(['busy_tables' => $busy_tables]);
$conn->close();
?>
