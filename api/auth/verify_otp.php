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

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thông tin xác thực không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!RateLimitService::check('verify_otp_' . hash('sha256', strtolower($email)), 5, 600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Bạn thử quá nhiều lần. Vui lòng đợi 10 phút.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = getDbConnection();
$stmt = $conn->prepare('SELECT id, otp_code, otp_expiry FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !hash_equals((string)$user['otp_code'], $otp) || strtotime($user['otp_expiry']) < time()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mã OTP không hợp lệ hoặc đã hết hạn'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Mã OTP hợp lệ'], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
