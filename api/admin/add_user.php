<?php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$name = $data['name'] ?? '';
$phone = $data['phone'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$role = $data['role'] ?? 'user';

if (!$name || !$phone || !$password) {
    echo json_encode(['success' => false, 'message' => 'Tên, SĐT và Mật khẩu là bắt buộc']);
    exit;
}

// Check duplicate phone
$conn = getDbConnection();
$check = $conn->prepare("SELECT id FROM users WHERE phone = ?");
$check->bind_param("s", $phone);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Số điện thoại đã tồn tại']);
    exit;
}

// Hash password (MD5 legacy or password_hash? existing generic login uses md5 or raw? 
// Checking login.php: It checks raw, then updates to hash. Ideally we use password_hash.)
// Let's use password_hash for security. generic login supports it.
$passHash = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (name, phone, email, password, role) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $name, $phone, $email, $passHash, $role);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Thêm người dùng thành công']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
