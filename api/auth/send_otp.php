<?php
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/config/mail_config.php';
require_once ROOT_PATH . '/lib/SimpleSMTP.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';
require_once ROOT_PATH . '/api/services/rate_limit_service.php';

// Rate limit: 3 lần / 60s để tránh OTP brute-force
if (!RateLimitService::check('send_otp', 3, 60)) {
    ResponseService::error('Quá nhiều yêu cầu. Vui lòng thử lại sau 1 phút.', 429);
}

// SEC-02: Validate CSRF
CsrfService::validateRequest();

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ResponseService::error('Email không hợp lệ', 400);
}

$conn = getDbConnection();

// 1. Check if email exists
$stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

// SEC-06: Trả message chung để tránh email enumeration attack
if ($res->num_rows === 0) {
    $stmt->close();
    $conn->close();
    // Vẫn trả success=true để attacker không biết email có tồn tại không
    ResponseService::success(['message' => 'Nếu email tồn tại, chúng tôi đã gửi mã OTP. Vui lòng kiểm tra hộp thư.']);
}

$user = $res->fetch_assoc();
$stmt->close();

// 2. SEC-07: Generate OTP dùng random_int() (CSPRNG, an toàn hơn rand())
$otp = random_int(100000, 999999);
$expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// 3. Save to DB
$update = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?");
$update->bind_param("ssi", $otp, $expiry, $user['id']);
$update->execute();
$update->close();
$conn->close();

// 4. Send Email
$mailer = new SimpleSMTP(MAIL_HOST, MAIL_USER, MAIL_PASS, MAIL_PORT);
$subject = "Mã xác thực lấy lại mật khẩu - Nhà Hàng Dượng Bầu";
$body = "
    <h3>Xin chào {$user['name']},</h3>
    <p>Bạn đã yêu cầu lấy lại mật khẩu.</p>
    <p>Mã xác thực (OTP) của bạn là: <b style='font-size:24px;color:#e67e22'>$otp</b></p>
    <p>Mã này có hiệu lực trong 10 phút. Tuyệt đối không chia sẻ cho ai.</p>
    <p>Trân trọng,<br>Nhà Hàng Dượng Bầu.</p>
";

if ($mailer->send($email, $subject, $body, MAIL_FROM_NAME)) {
    ResponseService::success(['message' => 'Nếu email tồn tại, chúng tôi đã gửi mã OTP. Vui lòng kiểm tra hộp thư.']);
} else {
    ResponseService::error('Lỗi gửi email. Vui lòng thử lại sau.', 500);
}
