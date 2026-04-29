<?php
function getDbConnection() {
    // Ensure constants are loaded if this file is called directly or without constants.php
    if (!defined('DB_HOST')) {
        require_once __DIR__ . '/constants.php';
    }

    $host = DB_HOST;
    $user = DB_USER;
    $pass = DB_PASS;
    $dbname = DB_NAME;

    $conn = new mysqli($host, $user, $pass, $dbname);

    // if ($conn->connect_error) {
    //     die("Kết nối thất bại: " . $conn->connect_error);
    // }

    // Đặt charset UTF-8mb4 (hỗ trợ Emoji và an toàn hơn)
    $conn->set_charset("utf8mb4");

    return $conn;
}
