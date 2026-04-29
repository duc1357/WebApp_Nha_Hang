<?php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$conn = getDbConnection();
$response = [];

// 1. Add deleted_at to users
$sqlUser = "SHOW COLUMNS FROM users LIKE 'deleted_at'";
$resultUser = $conn->query($sqlUser);
if ($resultUser->num_rows == 0) {
    $alterUser = "ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL";
    if ($conn->query($alterUser)) {
        $response['users'] = "Added deleted_at column to users table.";
    } else {
        $response['users'] = "Error adding column to users: " . $conn->error;
    }
} else {
    $response['users'] = "Column deleted_at already exists in users table.";
}

// 2. Add deleted_at to menu_items
$sqlMenu = "SHOW COLUMNS FROM menu_items LIKE 'deleted_at'";
$resultMenu = $conn->query($sqlMenu);
if ($resultMenu->num_rows == 0) {
    $alterMenu = "ALTER TABLE menu_items ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL";
    if ($conn->query($alterMenu)) {
        $response['menu_items'] = "Added deleted_at column to menu_items table.";
    } else {
        $response['menu_items'] = "Error adding column to menu_items: " . $conn->error;
    }
} else {
    $response['menu_items'] = "Column deleted_at already exists in menu_items table.";
}

$conn->close();
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
