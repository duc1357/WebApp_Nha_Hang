<?php
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';

$conn = getDbConnection();

$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? '';

if (!$date || !$time) {
    $conn->close();
    ResponseService::success(['booked' => [], 'details' => []]);
}

// Lấy tất cả các bookings chưa hủy trong ngày đó
$sql = "SELECT time, table_id, name FROM bookings WHERE date = ? AND status != 'cancelled'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

$booked = [];
$details = [];

while ($row = $result->fetch_assoc()) {
    $bookedTime = strtotime($date . ' ' . $row['time']);
    $requestTime = strtotime($date . ' ' . $time);

    // Check overlap +/- 2 hours
    $diff = abs($bookedTime - $requestTime);

    if ($diff < 7200) {
        $tableId = (int)$row['table_id'];
        if (!in_array($tableId, $booked)) {
            $booked[] = $tableId;
        }

        $startTime = date('H:i', $bookedTime);
        $endTime = date('H:i', $bookedTime + 7200);
        $details[$tableId] = [
            'time' => $row['time'],
            'duration' => "Đặt trước: $startTime - $endTime"
        ];
    }
}
$stmt->close();

// Kiểm tra thêm các order dine-in đang hoạt động (Tables currently eating)
// Logic: Orders created today that are still 'pending' (Khách chưa thanh toán)
$sqlO = "SELECT DISTINCT table_id FROM orders
         WHERE DATE(created_at) = ? AND table_id IS NOT NULL AND table_id != '' AND status = 'pending'";
$stmtO = $conn->prepare($sqlO);
$stmtO->bind_param("s", $date);
$stmtO->execute();
$resO = $stmtO->get_result();

while ($row = $resO->fetch_assoc()) {
    $tableId = (int)$row['table_id'];
    if (!in_array($tableId, $booked)) {
        $booked[] = $tableId;
    }
    $details[$tableId] = [
        'time' => 'Đang ăn',
        'duration' => 'Khách đang dùng bữa'
    ];
}
$stmtO->close();
$conn->close();

ResponseService::success([
    'booked' => $booked,
    'details' => $details
]);
