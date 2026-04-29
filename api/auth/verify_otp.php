<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';
$otp   = $data['otp'] ?? '';

if (!$email || !$otp) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đủ thông tin']);
    exit;
}

$conn = getDbConnection();

$stmt = $conn->prepare("SELECT id, otp_code, otp_expiry FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Email không tồn tại']);
    exit;
}

$user = $res->fetch_assoc();

if ($user['otp_code'] !== $otp) {
    echo json_encode(['success' => false, 'message' => 'Mã OTP không chính xác']);
    exit;
}

if (strtotime($user['otp_expiry']) < time()) {
    echo json_encode(['success' => false, 'message' => 'Mã OTP đã hết hạn']);
    exit;
}

// Success
echo json_encode(['success' => true, 'message' => 'Mã OTP hợp lệ']);

$conn->close();
