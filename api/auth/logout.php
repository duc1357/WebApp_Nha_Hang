<?php
require_once __DIR__ . '/../../config/constants.php';
header("Content-Type: application/json");

session_unset();
session_destroy();

echo json_encode([
    "success" => true,
    "message" => "Đã đăng xuất"
]);
?>
