<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/request_service.php';
require_once ROOT_PATH . '/api/services/validation_service.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';
require_once ROOT_PATH . '/api/services/rate_limit_service.php';
require_once ROOT_PATH . '/api/services/password_policy.php';

try {
    if (!RateLimitService::check('register', 10, 60)) {
        ResponseService::error('Thao tac qua nhanh. Vui long doi.', 429);
    }

    CsrfService::validateRequest();

    $data = RequestService::input(false);
    $name = ValidationService::requiredString($data, 'name', 'Vui long dien du Ho ten, SDT va Mat khau.');
    $phone = ValidationService::phone(
        ValidationService::requiredString($data, 'phone', 'Vui long dien du Ho ten, SDT va Mat khau.'),
        'So dien thoai khong hop le (10 so, bat dau bang 0)'
    );
    $email = ValidationService::email(
        ValidationService::requiredString($data, 'email', 'Email khong hop le'),
        'Email khong hop le'
    );
    $password = ValidationService::requiredString($data, 'password', 'Vui long dien du Ho ten, SDT va Mat khau.');

    if (!PasswordPolicy::isValid($password)) {
        ResponseService::error(PasswordPolicy::MESSAGE, 422);
    }

    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $conn = getDbConnection();

    $dupStmt = $conn->prepare("SELECT id FROM users WHERE phone = ? OR email = ? LIMIT 1");
    $dupStmt->bind_param("ss", $phone, $email);
    $dupStmt->execute();
    $dupResult = $dupStmt->get_result();
    if ($dupResult->num_rows > 0) {
        ResponseService::error('So dien thoai hoac Email da ton tai.', 409);
    }
    $dupStmt->close();

    $stmt = $conn->prepare(
        "INSERT INTO users (name, phone, email, password, role)
         VALUES (?, ?, ?, ?, 'customer')"
    );
    $stmt->bind_param("ssss", $safeName, $phone, $email, $hashedPassword);

    if (!$stmt->execute()) {
        if ($stmt->errno === 1062 || $conn->errno === 1062) {
            ResponseService::error('So dien thoai hoac Email da ton tai.', 409);
        }

        error_log('[Register] Insert failed: ' . $stmt->error);
        ResponseService::error('Loi he thong. Vui long thu lai sau.', 500);
    }

    $stmt->close();
    $conn->close();

    while (ob_get_level()) {
        ob_end_clean();
    }

    ResponseService::success(['message' => 'Dang ky thanh cong!']);
} catch (InvalidArgumentException $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    ResponseService::error($e->getMessage(), $e->getCode() ?: 400);
} catch (Throwable $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    error_log('[Register] ' . $e->getMessage());
    ResponseService::error('Loi he thong. Vui long thu lai sau.', 500);
}
