<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once __DIR__ . '/../../api/services/csrf_service.php';
require_once __DIR__ . '/../../api/services/rate_limit_service.php';
require_once __DIR__ . '/../../api/services/logger_service.php';

// Rate Limit: 10/min
if (!RateLimitService::check('admin_login', 10, 60)) {
    ResponseService::json(['success' => false, 'message' => 'Quá nhiều lần thử.'], 429);
    exit;
}

// CSRF
CsrfService::validateRequest();

// session_start handled by constants.php

$data = json_decode(file_get_contents("php://input"), true);
$email = isset($data['email']) ? trim($data['email']) : '';
$password = isset($data['password']) ? trim($data['password']) : '';

if (empty($email) || empty($password)) {
    ResponseService::json(['success' => false, 'message' => 'Vui lòng nhập email và mật khẩu'], 400);
    exit;
}

$conn = getDbConnection();

// Check email and role='admin'
$stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ? AND role = 'admin' AND deleted_at IS NULL");
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

        ResponseService::json(['success' => true, 'message' => 'Đăng nhập thành công']);
    } else {
        Logger::auth('Admin login failed - wrong password', ['email' => $email]);
        Logger::security('Admin brute-force attempt?', ['email' => $email]);
        ResponseService::json(['success' => false, 'message' => 'Email hoặc mật khẩu không đúng, hoặc tài khoản không có quyền truy cập.'], 401);
    }
} else {
    // Generic message – tránh user enumeration attack
    Logger::auth('Admin login failed - user not found or not admin', ['email' => $email]);
    ResponseService::json(['success' => false, 'message' => 'Email hoặc mật khẩu không đúng, hoặc tài khoản không có quyền truy cập.'], 401);
}

$stmt->close();
$conn->close();
