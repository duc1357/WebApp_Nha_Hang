<?php
// api/admin/update_user.php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'] ?? null;
if (!$id) { echo json_encode(['success' => false]); exit; }

$name = $data['name'] ?? null;
$phone = $data['phone'] ?? null;
$email = $data['email'] ?? null;
$password = $data['password'] ?? null;
$role = $data['role'] ?? null;

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
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
$conn->close();
