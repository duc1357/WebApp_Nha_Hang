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
    echo json_encode([
        "success" => false,
        "message" => "Tài khoản không tồn tại"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = $result->fetch_assoc();

// BẢO MẬT: Kiểm tra mật khẩu (Hashed hoặc Legacy)
// password_verify trả về true nếu khớp hash
// Nếu không khớp hash, kiểm tra xem có khớp plain text không (cho user cũ)
$is_valid = false;
if (password_verify($password, $user['password'])) {
    $is_valid = true;
} elseif ($password === $user['password']) {
    // Legacy plain text user -> Khớp
    $is_valid = true;
    // [SECURITY FIX] Dùng prepared statement thay vì string concatenation
    $newHash = password_hash($password, PASSWORD_BCRYPT);
    $upgradeStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $upgradeStmt->bind_param("si", $newHash, $user['id']);
    $upgradeStmt->execute();
    $upgradeStmt->close();
}

if (!$is_valid) {
    echo json_encode([
        "success" => false,
        "message" => "Sai mật khẩu"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Đăng nhập thành công
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
