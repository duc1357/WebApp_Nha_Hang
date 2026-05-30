<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
require_once dirname(__DIR__, 2) . '/api/services/pagination_service.php';
// api/menu/get_menu_list.php
if (ob_get_level()) ob_clean();
require_once dirname(__DIR__) . '/admin/auth_check_api.php';

require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$pagination = PaginationService::fromQuery($_GET, 10, 100);
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Base Query
$sql = "SELECT id, name, description, price, image_url, is_active, created_at FROM menu_items WHERE deleted_at IS NULL";
$types = "";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $types .= "ss";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status !== '') { // 0 or 1
    $sql .= " AND is_active = ?";
    $types .= "i";
    $params[] = (int)$status;
}

// Count Total
$countSql = str_replace("SELECT id, name, description, price, image_url, is_active, created_at", "SELECT COUNT(*) as total", $sql);
$stmtC = $conn->prepare($countSql);
if (!empty($params)) {
    $stmtC->bind_param($types, ...$params);
}
$stmtC->execute();
$totalItems = $stmtC->get_result()->fetch_assoc()['total'];
$stmtC->close();

// Sort & Limit
$sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();
$conn->close();

ResponseService::json([
    'success' => true,
    'items'   => $items,
    'pagination' => [
        'total' => $totalItems,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => PaginationService::totalPages($totalItems, $limit)
    ]
]);
