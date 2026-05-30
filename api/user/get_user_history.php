<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/pagination_service.php';
require_once ROOT_PATH . '/api/services/auth_state_service.php';

// Check if user is logged in
$authUser = AuthStateService::requireSession();

$conn = getDbConnection();

// Get user info from session
$userId = (int)$authUser['id'];
// Optional: also get phone from session if needed, but userId is safer for foreign keys if available.
// Hien tai DB orders co user_id, bookings co phone.

$userPhone = '';
// Tim phone tu user_id de query bookings
$uSql = "SELECT phone FROM users WHERE id = ?";
$stmt = $conn->prepare($uSql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
if ($r = $res->fetch_assoc()) {
    $userPhone = $r['phone'];
}
$stmt->close();


// 1. Get Orders using user_id with Pagination
$orderPagination = PaginationService::fromQuery([
    'page' => $_GET['o_page'] ?? 1,
    'limit' => 3,
], 3, 3);
$orderPage = $orderPagination['page'];
$limit = $orderPagination['limit'];
$orderOffset = $orderPagination['offset'];

$orders = [];
$totalOrders = 0;

if ($userId) {
    // Count total orders
    $countSql = "SELECT COUNT(*) as total FROM orders WHERE user_id = ?";
    $stmtC = $conn->prepare($countSql);
    $stmtC->bind_param("i", $userId);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    if ($rowC = $resC->fetch_assoc()) {
        $totalOrders = $rowC['total'];
    }
    $stmtC->close();

    // Get paginated orders
    $oSql = "SELECT o.id, o.total_amount, o.discount_amount, o.final_total, o.payment_method, o.status, o.created_at,
            (SELECT COUNT(*) FROM reviews r WHERE r.order_id = o.id) as is_reviewed
            FROM orders o
            WHERE o.user_id = ?
            ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($oSql);
    $stmt->bind_param("iii", $userId, $limit, $orderOffset);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
}

// 2. Get Bookings using user_id with Pagination
$bookingPagination = PaginationService::fromQuery([
    'page' => $_GET['b_page'] ?? 1,
    'limit' => 3,
], 3, 3);
$bookingPage = $bookingPagination['page'];
$bookingLimit = $bookingPagination['limit'];
$bookingOffset = $bookingPagination['offset'];

$bookings = [];
$totalBookings = 0;

if ($userId) {
    // Count total bookings
    $countSql = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ?";
    $stmtC = $conn->prepare($countSql);
    $stmtC->bind_param("i", $userId);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    if ($rowC = $resC->fetch_assoc()) {
        $totalBookings = $rowC['total'];
    }
    $stmtC->close();
    // JOIN with tables to get the real name logic
    // MODIFIED: Filter by user_id to ensure privacy
    $bSql = "SELECT b.id, b.date, b.time, b.guests, b.floor, b.table_number, b.created_at, b.status,
                    t.name AS table_name
             FROM bookings b
             LEFT JOIN tables t ON b.table_id = t.id
             WHERE b.user_id = ?
             ORDER BY b.date DESC, b.time DESC LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($bSql);
    $stmt->bind_param("iii", $userId, $bookingLimit, $bookingOffset);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $bookings[] = $row;
    }
    $stmt->close();
}

$conn->close();

ResponseService::success([
    'orders' => $orders,
    'bookings' => $bookings,
    'pagination' => [
        'total_orders' => $totalOrders,
        'limit' => $limit,
        'current_page' => $orderPage,
        'total_pages' => PaginationService::totalPages($totalOrders, $limit)
    ],
    'booking_pagination' => [
        'total_bookings' => $totalBookings,
        'limit' => $bookingLimit,
        'current_page' => $bookingPage,
        'total_pages' => PaginationService::totalPages($totalBookings, $bookingLimit)
    ]
]);
