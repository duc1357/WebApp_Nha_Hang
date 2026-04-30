<?php
function getDbConnection() {
    // Ensure constants are loaded if this file is called directly or without constants.php
    if (!defined('DB_HOST')) {
        require_once __DIR__ . '/constants.php';
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        // Log error internally, never expose to client
        error_log('[DB] Connection failed: ' . $conn->connect_error);
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Lỗi kết nối hệ thống. Vui lòng thử lại sau.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Đặt charset UTF-8mb4 (hỗ trợ Emoji và an toàn hơn)
    $conn->set_charset('utf8mb4');

    return $conn;
}
