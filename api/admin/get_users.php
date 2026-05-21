<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
// api/admin/get_users.php
ob_clean();
require_once __DIR__ . '/auth_check_api.php';
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
    if (($row['role'] ?? '') === 'customer') {
        $row['role'] = 'user';
    }
    $users[] = $row;
}

ResponseService::json([
    'success' => true,
    'users'   => $users
]);

$conn->close();
