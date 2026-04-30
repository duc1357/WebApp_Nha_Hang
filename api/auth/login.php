<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once __DIR__ . '/../../api/services/csrf_service.php';
require_once __DIR__ . '/../../api/services/rate_limit_service.php';

// Rate Limit: 20 attempts / 60s
if (!RateLimitService::check('login', 20, 60)) {
    http_response_code(429);
    echo json_encode(["success" => false, "message" => "Quá nhiều lần thử. Vui lòng đợi 1 phút."]);
    exit;
}

CsrfService::validateRequest();

$conn = getDbConnection();

// Đọc JSON từ body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Lấy identifier (email hoặc SĐT) và mật khẩu
$identifier = trim($data['identifier'] ?? '');
$password   = trim($data['password'] ?? '');

if ($identifier === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Thiếu email/SĐT hoặc mật khẩu"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Tìm user theo email hoặc phone
$sql = "SELECT id, name, email, phone, password, role, avatar 
        FROM users 
        WHERE email = ? OR phone = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $identifier, $identifier);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Email/SĐT hoặc mật khẩu không đúng"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = $result->fetch_assoc();

// BẢO MẬT: Kiểm tra mật khẩu (BCrypt hash)
$is_valid = password_verify($password, $user['password']);

// Note: Legacy plain-text fallback đã bị xóa. Chạy migration script nếu có user cỳ chưa được hash.

if (!$is_valid) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Email/SĐT hoặc mật khẩu không đúng"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Đăng nhập thành công
// SEC-08: Regenerate session ID để ngăn session fixation attack
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['name']    = $user['name'];
$_SESSION['role']    = $user['role'];
// Rotate CSRF token sau login (ngăn session fixation và token reuse)
CsrfService::rotateToken();

echo json_encode([
    "success" => true,
    "message" => "Đăng nhập thành công",
    "user" => [
        "id"    => $user['id'],
        "name"  => $user['name'],
        "email" => $user['email'],
        "phone" => $user['phone'],
        "avatar" => $user['avatar'],
        "role"  => $user['role']
    ]
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
