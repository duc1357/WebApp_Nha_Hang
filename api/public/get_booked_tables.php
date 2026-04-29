<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$conn = getDbConnection();

$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? '';

if (!$date || !$time) {
    echo json_encode([]);
    exit;
}

// Giả sử mỗi bàn đặt khoảng 2 tiếng. 
// Nếu khách đặt lúc 18:00 thì bàn đó bận đến 20:00.
// Nếu khách khác vào đặt lúc 19:00 thì vẫn bị coi là trùng.
// Để đơn giản cho demo: Mình check trùng giờ chính xác hoặc +/- 1 tiếng.
// Logic đơn giản: Lấy tất cả bookings trong ngày đó.

// Check bookings that are NOT cancelled
$sql = "SELECT time, table_id FROM bookings WHERE date = ? AND status != 'cancelled'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

$booked = [];
while ($row = $result->fetch_assoc()) {
    $bookedTime = strtotime($date . ' ' . $row['time']);
    $requestTime = strtotime($date . ' ' . $time);
    
    // Check overlap +/- 2 hours
    $diff = abs($bookedTime - $requestTime);
    
    if ($diff < 7200) {
        $booked[] = (int)$row['table_id']; // Push only ID
    }
}

echo json_encode($booked);
$stmt->close();
$conn->close();
