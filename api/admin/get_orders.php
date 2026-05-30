<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
require_once dirname(__DIR__, 2) . '/api/services/pagination_service.php';
// api/admin/get_orders.php
require_once __DIR__ . '/auth_check_api.php';
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$pagination = PaginationService::fromQuery($_GET, 10, 100);
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Base Query
$sql = "SELECT
            o.id,
            o.total_amount,
            o.discount_amount,
            o.final_total,
            o.payment_method,
            o.status,
            o.created_at,
            o.address,
            o.table_id,
            o.note,
            u.name  AS customer_name,
            u.phone AS customer_phone
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE 1=1";

$types = "";
$params = [];

// Apply Filters
if (!empty($search)) {
    if (is_numeric($search)) {
        $sql .= " AND o.id = ?";
        $types .= "i";
        $params[] = $search;
    } else {
        $sql .= " AND (u.name LIKE ? OR u.phone LIKE ?)";
        $types .= "ss";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}

if (!empty($status)) {
    $sql .= " AND o.status = ?";
    $types .= "s";
    $params[] = $status;
}

// Count Total for Pagination
// Count Total for Pagination
$countSql = "SELECT COUNT(*) as total FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE 1=1";
if (!empty($search)) {
    if (is_numeric($search)) {
        $countSql .= " AND o.id = ?";
    } else {
        $countSql .= " AND (u.name LIKE ? OR u.phone LIKE ?)";
    }
}
if (!empty($status)) {
    $countSql .= " AND o.status = ?";
}

$stmtC = $conn->prepare($countSql);
if (!empty($params)) {
    $stmtC->bind_param($types, ...$params);
}
$stmtC->execute();
$totalOrders = $stmtC->get_result()->fetch_assoc()['total'];
$stmtC->close();

// Add Sort & Limit
$sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

ResponseService::json([
    'success' => true,
    'orders'  => $orders,
    'pagination' => [
        'total' => $totalOrders,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => PaginationService::totalPages($totalOrders, $limit)
    ]
]);

$conn->close();
