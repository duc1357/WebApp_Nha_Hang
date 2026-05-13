<?php
// api/admin/update_user.php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$data = json_decode(file_get_contents("php://input"), true);

$id = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim($data['name'] ?? '');
$phone = trim($data['phone'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? null;
$role = trim($data['role'] ?? '');
if ($role === 'user') {
    $role = 'customer';
}

$allowedRoles = ['admin', 'customer'];
if (!in_array($role, $allowedRoles, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Role không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($id === (int)$_SESSION['user_id'] && $role !== 'admin') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Không thể tự hạ quyền admin của chính mình'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^0[0-9]{9}$/', $phone)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Thông tin người dùng không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!empty($password) && strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 8 ký tự'], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "UPDATE users SET name=?, phone=?, email=?, role=?";
$types = "ssss";
$params = [$name, $phone, $email, $role];

if (!empty($password)) {
    $sql .= ", password=?";
    $types .= "s";
    $params[] = password_hash($password, PASSWORD_BCRYPT);
}

$sql .= " WHERE id=?";
$types .= "i";
$params[] = $id;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Cập nhật người dùng thành công'], JSON_UNESCAPED_UNICODE);
} else {
    error_log('[AdminUpdateUser] ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Không thể cập nhật người dùng'], JSON_UNESCAPED_UNICODE);
}
$conn->close();
