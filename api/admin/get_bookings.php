<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
ob_clean();
require_once __DIR__ . '/auth_check_api.php';
header('X-Content-Type-Options: nosniff');

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$conn = getDbConnection();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$date = isset($_GET['date']) ? trim($_GET['date']) : '';

// Base Query
$sql = "SELECT
            bookings.id,
            bookings.name,
            bookings.phone,
            TIME_FORMAT(bookings.time, '%H:%i') AS time,
            bookings.date,
            bookings.guests,
            bookings.status,
            bookings.created_at,
            tables.name AS table_name
        FROM bookings
        LEFT JOIN tables ON bookings.table_id = tables.id
        WHERE 1=1";

$types = "";
$params = [];

// Apply Filters
if (!empty($search)) {
    $sql .= " AND (bookings.name LIKE ? OR bookings.phone LIKE ?)";
    $types .= "ss";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $sql .= " AND bookings.status = ?";
    $types .= "s";
    $params[] = $status;
}

if (!empty($date)) {
    $sql .= " AND bookings.date = ?";
    $types .= "s";
    $params[] = $date;
}

// Count Total
$countSql = str_replace("SELECT
            bookings.id,
            bookings.name,
            bookings.phone,
            TIME_FORMAT(bookings.time, '%H:%i') AS time,
            bookings.date,
            bookings.guests,
            bookings.status,
            bookings.created_at,
            tables.name AS table_name", "SELECT COUNT(*) as total", $sql);

$stmtC = $conn->prepare($countSql);
if (!empty($params)) {
    $stmtC->bind_param($types, ...$params);
}
$stmtC->execute();
$totalBookings = $stmtC->get_result()->fetch_assoc()['total'];
$stmtC->close();

// Sort & Limit
$sql .= " ORDER BY bookings.date DESC, bookings.time DESC LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

$stmt->close();
$conn->close();

ResponseService::json([
    'success'  => true,
    'bookings' => $bookings,
    'pagination' => [
        'total' => $totalBookings,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($totalBookings / $limit)
    ]
]);
