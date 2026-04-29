<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/config/mail_config.php';
require_once ROOT_PATH . '/lib/SimpleSMTP.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email không hợp lệ']);
    exit;
}

$conn = getDbConnection();

// 1. Check if email exists
$stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Email không tồn tại trong hệ thống']);
    exit;
}

$user = $res->fetch_assoc();

// 2. Generate OTP
$otp = rand(100000, 999999);
$expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// 3. Save to DB
$update = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?");
$update->bind_param("ssi", $otp, $expiry, $user['id']);
$update->execute();

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
    echo json_encode(['success' => true, 'message' => 'Đã gửi mã OTP đến email của bạn']);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Lỗi gửi email: ' . $mailer->error
    ]);
}

$conn->close();
