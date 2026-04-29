<?php
// api/admin/get_users.php
ob_clean();
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$sql = "SELECT id, name, phone, email, role, created_at
        FROM users
        WHERE deleted_at IS NULL
        ORDER BY created_at DESC, id DESC
        LIMIT 100";

$result = $conn->query($sql);
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

echo json_encode([
    'success' => true,
    'users'   => $users
]);

$conn->close();
