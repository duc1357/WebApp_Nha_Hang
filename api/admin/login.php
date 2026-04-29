<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once __DIR__ . '/../../api/services/csrf_service.php';
require_once __DIR__ . '/../../api/services/rate_limit_service.php';

// Rate Limit: 10/min
if (!RateLimitService::check('admin_login', 10, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Qúa nhiều lần thử.']);
    exit;
}

// CSRF
CsrfService::validateRequest();

// session_start handled by constants.php

$data = json_decode(file_get_contents("php://input"), true);
$email = isset($data['email']) ? trim($data['email']) : '';
$password = isset($data['password']) ? trim($data['password']) : '';

if (empty($email) || empty($password)) {
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
        // Success
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role']; // 'admin'

        echo json_encode(['success' => true, 'message' => 'Đăng nhập thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu không đúng']);
    }
} else {
    // Check if user exists but not admin
    $stmt2 = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt2->bind_param("s", $email);
    $stmt2->execute();
    if ($stmt2->get_result()->num_rows > 0) {
         echo json_encode(['success' => false, 'message' => 'Tài khoản không có quyền Admin']);
    } else {
         echo json_encode(['success' => false, 'message' => 'Email không tồn tại']);
    }
    $stmt2->close();
}

$stmt->close();
$conn->close();
?>
