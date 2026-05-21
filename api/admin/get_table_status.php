<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
// api/admin/get_table_status.php
require_once __DIR__ . '/auth_check_api.php';

require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$time = isset($_GET['time']) ? $_GET['time'] : date('H:i'); // Optional time filter

// 1. Get All Tables
$tables = [];
$sql = "SELECT id, name, floor, capacity, status FROM tables ORDER BY floor ASC, id ASC"; // Included manual status
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $tables[$row['id']] = $row;
        $tables[$row['id']]['booking_info'] = null;
        $tables[$row['id']]['source'] = 'none'; // Default

        if ($row['status'] === 'occupied' && $date === date('Y-m-d')) {
             $tables[$row['id']]['booking_info'] = ['name' => 'Khách vãng lai', 'time' => 'Trực tiếp'];
             $tables[$row['id']]['source'] = 'manual';
        }
    }
}

// 2. Get Confirmed Bookings for Date
$sqlB = "SELECT id, table_id, name, time, guests, status
         FROM bookings
         WHERE date = ? AND status = 'confirmed'";
$stmt = $conn->prepare($sqlB);
$stmt->bind_param("s", $date);
$stmt->execute();
$resB = $stmt->get_result();

while ($row = $resB->fetch_assoc()) {
    $tid = $row['table_id'];
    if (isset($tables[$tid])) {
        $tables[$tid]['status'] = 'occupied';
        $tables[$tid]['booking_info'] = $row;
        $tables[$tid]['source'] = 'booking';
    }
}

// 3. Get Active Orders
$sqlO = "SELECT id, table_id, status FROM orders
         WHERE table_id IS NOT NULL AND table_id != '' AND status = 'pending' AND DATE(created_at) = ?";
$stmtO = $conn->prepare($sqlO);
$stmtO->bind_param("s", $date);
$stmtO->execute();
$resO = $stmtO->get_result();

while ($row = $resO->fetch_assoc()) {
    $tName = $row['table_id'];
    foreach ($tables as &$t) {
        if ($t['id'] == $tName || $t['name'] == $tName) {
            $t['status'] = 'occupied';
            $t['booking_info'] = ['name' => 'Đang ăn', 'time' => 'Now'];
            $t['source'] = 'order';
        }
    }
}

// Group by Floor
$floors = [];
foreach ($tables as $t) {
    $floor = $t['floor']; // Rose / Tulip
    if (!isset($floors[$floor])) {
        $floors[$floor] = [];
    }
    $floors[$floor][] = $t;
}

ResponseService::json([
    'success' => true,
    'date' => $date,
    'floors' => $floors
]);

$conn->close();
?>
