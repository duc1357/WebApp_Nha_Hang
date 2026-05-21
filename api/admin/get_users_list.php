<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
// api/admin/get_users_list.php
ob_clean();
require_once __DIR__ . '/auth_check_api.php';
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role = isset($_GET['role']) ? trim($_GET['role']) : '';
if ($role === 'user') {
    $role = 'customer';
}

// Base Query
$sql = "SELECT id, name, phone, email, role, created_at FROM users WHERE deleted_at IS NULL";
$types = "";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $types .= "sss";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($role)) {
    $sql .= " AND role = ?";
    $types .= "s";
    $params[] = $role;
}

// Count Total
$countSql = str_replace("SELECT id, name, phone, email, role, created_at", "SELECT COUNT(*) as total", $sql);
$stmtC = $conn->prepare($countSql);
if (!empty($params)) {
    $stmtC->bind_param($types, ...$params);
}
$stmtC->execute();
$totalUsers = $stmtC->get_result()->fetch_assoc()['total'];
$stmtC->close();

// Sort & Limit
$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    if (($row['role'] ?? '') === 'customer') {
        $row['role'] = 'user';
    }
    $users[] = $row;
}
$stmt->close();
$conn->close();

ResponseService::json([
    'success' => true,
    'users'   => $users,
    'pagination' => [
        'total' => $totalUsers,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($totalUsers / $limit)
    ]
]);
