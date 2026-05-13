<?php
// api/admin/create_user.php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim($data['name'] ?? '');
$phone = trim($data['phone'] ?? '');
$email = trim($data['email'] ?? '');
$password = (string)($data['password'] ?? '');
$role = trim($data['role'] ?? 'customer');
if ($role === 'user') $role = 'customer';

$allowedRoles = ['admin', 'customer'];
if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, $allowedRoles, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Thông tin người dùng không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!preg_match('/^0[0-9]{9}$/', $phone)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Số điện thoại không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 8 ký tự'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = getDbConnection();
$hashed = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare('INSERT INTO users (name, phone, email, password, role) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('sssss', $name, $phone, $email, $hashed, $role);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Tạo người dùng thành công'], JSON_UNESCAPED_UNICODE);
} else {
    error_log('[AdminCreateUser] ' . $stmt->error);
    http_response_code($conn->errno === 1062 ? 409 : 500);
    echo json_encode(['success' => false, 'message' => 'Số điện thoại hoặc email có thể đã tồn tại'], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
