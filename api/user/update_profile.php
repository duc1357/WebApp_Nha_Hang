<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/api/base.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';

// Auth: chỉ user đã đăng nhập mới được cập nhật
if (!isset($_SESSION['user_id'])) {
    apiError('Vui lòng đăng nhập', 401);
}

requireMethod('POST');
CsrfService::validateRequest();

$data = getJsonBody(required: true);
$id   = (int) $_SESSION['user_id'];

// [3.4] Validate với length constraints
$name  = getParam($data, 'name',  null, 'string');
$email = getParam($data, 'email', null, 'email');
$phone = trim($data['phone'] ?? '');

if (!$name || strlen($name) < 2 || strlen($name) > 100) {
    apiError('Tên phải có từ 2 đến 100 ký tự');
}
if (!$email) {
    apiError('Email không hợp lệ');
}
if (!preg_match('/^0[35789][0-9]{8}$/', $phone)) {
    apiError('Số điện thoại không hợp lệ (10 số, bắt đầu 03/05/07/08/09)');
}

require_once ROOT_PATH . '/config/db.php';
$conn = getDbConnection();

// [3.4] Kiểm tra email đã được dùng bởi user khác chưa
$checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
$checkStmt->bind_param('si', $email, $id);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    apiError('Email này đã được sử dụng bởi tài khoản khác');
}
$checkStmt->close();

$stmt = $conn->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?');
$stmt->bind_param('sssi', $name, $email, $phone, $id);

if ($stmt->execute()) {
    apiSuccess(null, 'Cập nhật thông tin thành công');
} else {
    apiError('Lỗi cập nhật thông tin', 500);
}

$stmt->close();
$conn->close();
