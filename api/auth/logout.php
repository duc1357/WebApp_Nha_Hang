<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../api/services/csrf_service.php';
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
    exit;
}

CsrfService::validateRequest();

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($userId > 0) {
    require_once ROOT_PATH . '/config/db.php';
    $conn = getDbConnection();
    $stmt = $conn->prepare('UPDATE users SET token_version = token_version + 1 WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
}

session_unset();
session_destroy();

echo json_encode([
    "success" => true,
    "message" => "Đã đăng xuất"
]);
?>
