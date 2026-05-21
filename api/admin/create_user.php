<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
// api/admin/create_user.php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();

require_once __DIR__ . '/../../config/db.php';
require_once dirname(__DIR__, 2) . '/api/services/password_policy.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    ResponseService::json(['success' => false, 'message' => 'JSON không hợp lệ'], 400);
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
    ResponseService::json(['success' => false, 'message' => 'Thông tin người dùng không hợp lệ'], 422);
    exit;
}
if (!preg_match('/^0[0-9]{9}$/', $phone)) {
    ResponseService::json(['success' => false, 'message' => 'Số điện thoại không hợp lệ'], 422);
    exit;
}
if (!PasswordPolicy::isValid($password)) {
    ResponseService::json(['success' => false, 'message' => PasswordPolicy::MESSAGE], 422);
    exit;
}

$conn = getDbConnection();
$hashed = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare('INSERT INTO users (name, phone, email, password, role) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('sssss', $name, $phone, $email, $hashed, $role);

if ($stmt->execute()) {
    ResponseService::json(['success' => true, 'message' => 'Tạo người dùng thành công']);
} else {
    error_log('[AdminCreateUser] ' . $stmt->error);
    ResponseService::json(['success' => false, 'message' => 'Số điện thoại hoặc email có thể đã tồn tại'], $conn->errno === 1062 ? 409 : 500);
}

$stmt->close();
$conn->close();
