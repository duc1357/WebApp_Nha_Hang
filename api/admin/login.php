<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once __DIR__ . '/../../api/services/csrf_service.php';
require_once __DIR__ . '/../../api/services/rate_limit_service.php';
require_once __DIR__ . '/../../api/services/logger_service.php';

// Rate Limit: 10/min
if (!RateLimitService::check('admin_login', 10, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Quá nhiều lần thử.']);
    exit;
}

// CSRF
CsrfService::validateRequest();

// session_start handled by constants.php

$data = json_decode(file_get_contents("php://input"), true);
$email = isset($data['email']) ? trim($data['email']) : '';
$password = isset($data['password']) ? trim($data['password']) : '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập email và mật khẩu']);
    exit;
}

$conn = getDbConnection();

// Check email and role='admin'
$stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ? AND role = 'admin'");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        // SEC-08: Regenerate session ID để ngăn session fixation attack
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role']; // 'admin'
        CsrfService::rotateToken();
        Logger::auth('Admin login success', ['user_id' => $user['id'], 'email' => $email]);

        echo json_encode(['success' => true, 'message' => 'Đăng nhập thành công']);
    } else {
        Logger::auth('Admin login failed - wrong password', ['email' => $email], 'auth');
        Logger::security('Admin brute-force attempt?', ['email' => $email]);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Email hoặc mật khẩu không đúng, hoặc tài khoản không có quyền truy cập.']);
    }
} else {
    // Generic message – tránh user enumeration attack
    Logger::auth('Admin login failed - user not found or not admin', ['email' => $email]);
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Email hoặc mật khẩu không đúng, hoặc tài khoản không có quyền truy cập.']);
}

$stmt->close();
$conn->close();
