<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';
require_once ROOT_PATH . '/api/services/rate_limit_service.php';

CsrfService::validateRequest();

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$otp = trim($data['otp'] ?? '');
$pass = (string)($data['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otp) || $pass === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu hoặc sai thông tin xác thực'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!RateLimitService::check('reset_password_' . hash('sha256', strtolower($email)), 5, 600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Bạn thử quá nhiều lần. Vui lòng đợi 10 phút.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($pass) < 8 || !preg_match('/[A-Z]/', $pass) || !preg_match('/\d/', $pass)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Mật khẩu cần tối thiểu 8 ký tự, có chữ hoa và số.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = getDbConnection();
$stmt = $conn->prepare('SELECT id, otp_code, otp_expiry FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !hash_equals((string)$user['otp_code'], $otp) || strtotime($user['otp_expiry']) < time()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mã OTP không hợp lệ hoặc đã hết hạn'], JSON_UNESCAPED_UNICODE);
    exit;
}

$hashed = password_hash($pass, PASSWORD_BCRYPT);
$update = $conn->prepare('UPDATE users SET password = ?, otp_code = NULL, otp_expiry = NULL, token_version = token_version + 1 WHERE id = ?');
$update->bind_param('si', $hashed, $user['id']);

if ($update->execute()) {
    echo json_encode(['success' => true, 'message' => 'Đặt lại mật khẩu thành công!'], JSON_UNESCAPED_UNICODE);
} else {
    error_log('[ResetPassword] ' . $update->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Không thể cập nhật mật khẩu'], JSON_UNESCAPED_UNICODE);
}

$update->close();
$conn->close();
