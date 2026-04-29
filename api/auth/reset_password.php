<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';
$otp   = $data['otp'] ?? '';
$pass  = $data['password'] ?? '';

if (!$email || !$otp || !$pass) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin']);
    exit;
}

$conn = getDbConnection();

// Verify OTP again (Secure step)
$stmt = $conn->prepare("SELECT id, otp_code, otp_expiry FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user || $user['otp_code'] !== $otp || strtotime($user['otp_expiry']) < time()) {
    echo json_encode(['success' => false, 'message' => 'Mã OTP không hợp lệ hoặc hết hạn']);
    exit;
}

// Update Password & Clear OTP
$hashed = password_hash($pass, PASSWORD_BCRYPT);
$update = $conn->prepare("UPDATE users SET password = ?, otp_code = NULL, otp_expiry = NULL WHERE id = ?");
$update->bind_param("si", $hashed, $user['id']);

if ($update->execute()) {
    echo json_encode(['success' => true, 'message' => 'Đặt lại mật khẩu thành công!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật mật khẩu']);
}

$conn->close();
