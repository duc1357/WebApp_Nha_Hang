<?php
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';
require_once ROOT_PATH . '/api/services/rate_limit_service.php';

CsrfService::validateRequest();

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$otp = trim($data['otp'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otp)) {
    ResponseService::error('Thông tin xác thực không hợp lệ', 400);
}

if (!RateLimitService::check('verify_otp_' . hash('sha256', strtolower($email)), 5, 600)) {
    ResponseService::error('Bạn thử quá nhiều lần. Vui lòng đợi 10 phút.', 429);
}

$conn = getDbConnection();
$stmt = $conn->prepare('SELECT id, otp_code, otp_expiry FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !hash_equals((string)$user['otp_code'], $otp) || strtotime($user['otp_expiry']) < time()) {
    ResponseService::error('Mã OTP không hợp lệ hoặc đã hết hạn', 400);
}

$stmt->close();
$conn->close();
ResponseService::success(['message' => 'Mã OTP hợp lệ']);
