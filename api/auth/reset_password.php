<?php
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';
require_once ROOT_PATH . '/api/services/rate_limit_service.php';
require_once ROOT_PATH . '/api/services/password_policy.php';

CsrfService::validateRequest();

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$otp = trim($data['otp'] ?? '');
$pass = (string)($data['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otp) || $pass === '') {
    ResponseService::error('Thiếu hoặc sai thông tin xác thực', 400);
}

if (!RateLimitService::check('reset_password_' . hash('sha256', strtolower($email)), 5, 600)) {
    ResponseService::error('Bạn thử quá nhiều lần. Vui lòng đợi 10 phút.', 429);
}

if (!PasswordPolicy::isValid($pass)) {
    ResponseService::error('Mật khẩu cần tối thiểu 8 ký tự, có chữ hoa và số.', 422);
}

$conn = getDbConnection();
$stmt = $conn->prepare('SELECT id, otp_code, otp_expiry FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !hash_equals((string)$user['otp_code'], $otp) || strtotime($user['otp_expiry']) < time()) {
    ResponseService::error('Mã OTP không hợp lệ hoặc đã hết hạn', 400);
}

$hashed = password_hash($pass, PASSWORD_BCRYPT);
$update = $conn->prepare('UPDATE users SET password = ?, otp_code = NULL, otp_expiry = NULL, token_version = token_version + 1 WHERE id = ?');
$update->bind_param('si', $hashed, $user['id']);

if ($update->execute()) {
    $update->close();
    $conn->close();
    ResponseService::success(['message' => 'Đặt lại mật khẩu thành công!']);
} else {
    error_log('[ResetPassword] ' . $update->error);
    $update->close();
    $conn->close();
    ResponseService::error('Không thể cập nhật mật khẩu', 500);
}
