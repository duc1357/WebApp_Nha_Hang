<?php
// api/admin/create_user.php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$data = json_decode(file_get_contents("php://input"), true);

$name = $data['name'] ?? '';
$phone = $data['phone'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$role = $data['role'] ?? 'user';

if (!$name || !$phone || !$password) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Hash password
$hashed = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO users (name, phone, email, password, role) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $name, $phone, $email, $hashed, $role);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'SĐT hoặc Email có thể đã tồn tại']);
}
$conn->close();
