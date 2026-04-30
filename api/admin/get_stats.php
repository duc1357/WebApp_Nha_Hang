<?php
// CODE-07: Guard ob_clean() với ob_get_level()
if (ob_get_level()) ob_clean();
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$conn = getDbConnection();

$today = date('Y-m-d');

$todayRevenue   = 0;
$todayOrders    = 0;
$pendingOrders  = 0;
$todayBookings  = 0;

/* 1. Doanh thu hôm nay: SUM các đơn đã thanh toán (paid) trong ngày */
$sql = "
    SELECT COALESCE(SUM(CASE WHEN final_total > 0 THEN final_total ELSE total_amount END), 0) AS rev
    FROM orders
    WHERE status = 'paid'
      AND DATE(created_at) = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $todayRevenue = (int)$row['rev'];
}
$stmt->close();

/* 2. Đơn hàng hôm nay: COUNT tất cả đơn trong ngày (mọi trạng thái) */
$sql = "
    SELECT COUNT(*) AS c
    FROM orders
    WHERE DATE(created_at) = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $todayOrders = (int)$row['c'];
}
$stmt->close();

/* 3. Đơn đang chờ: status = 'pending'  */
$sql = "
    SELECT COUNT(*) AS c
    FROM orders
    WHERE status = 'pending'
";
$res = $conn->query($sql);
if ($res && ($row = $res->fetch_assoc())) {
    $pendingOrders = (int)$row['c'];
}
if ($res) $res->free();

/* 4. Đặt bàn hôm nay: COUNT(*) theo cột `date` */
$sql = "
    SELECT COUNT(*) AS c
    FROM bookings
    WHERE `date` = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $todayBookings = (int)$row['c'];
}
$stmt->close();


echo json_encode([
    'success'        => true,
    'today_revenue'  => $todayRevenue,
    'today_orders'   => $todayOrders,
    'today_bookings' => $todayBookings,
    'pending_orders' => $pendingOrders
], JSON_UNESCAPED_UNICODE);
