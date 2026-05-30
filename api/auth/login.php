<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/request_service.php';
require_once ROOT_PATH . '/api/services/validation_service.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';
require_once ROOT_PATH . '/api/services/rate_limit_service.php';
require_once ROOT_PATH . '/api/services/jwt_service.php';
require_once ROOT_PATH . '/api/services/logger_service.php';

try {
    if (!RateLimitService::check('login', 20, 60)) {
        Logger::security('User login rate limit exceeded', [], Logger::WARNING);
        ResponseService::error('Quá nhiều lần thử. Vui lòng đợi 1 phút.', 429);
    }

    CsrfService::validateRequest();

    $data = RequestService::json(true);
    $identifier = ValidationService::requiredString($data, 'identifier', 'Thiếu Email/SĐT hoặc mật khẩu.');
    $password = ValidationService::requiredString($data, 'password', 'Thiếu Email/SĐT hoặc mật khẩu.');

    $conn = getDbConnection();

    $sql = "SELECT id, name, email, phone, password, role, avatar, token_version
            FROM users
            WHERE (email = ? OR phone = ?) AND deleted_at IS NULL
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        Logger::auth('User login failed - user not found', [
            'identifier_hash' => hash('sha256', strtolower($identifier)),
        ]);
        ResponseService::error('Email/SĐT hoặc mật khẩu không đúng.', 401);
    }

    $user = $result->fetch_assoc();

    if (!password_verify($password, $user['password'])) {
        Logger::auth('User login failed - wrong password', [
            'user_id' => (int)$user['id'],
            'identifier_hash' => hash('sha256', strtolower($identifier)),
        ]);
        ResponseService::error('Email/SĐT hoặc mật khẩu không đúng.', 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['token_version'] = (int)($user['token_version'] ?? 0);
    CsrfService::rotateToken();

    $jwt = JwtService::generate([
        'user_id' => (int)$user['id'],
        'role' => $user['role'],
        'token_version' => (int)($user['token_version'] ?? 0),
    ]);

    Logger::auth('User login success', [
        'user_id' => (int)$user['id'],
        'role' => $user['role'],
    ]);

    $stmt->close();
    $conn->close();

    ResponseService::success([
        'message' => 'Đăng nhập thành công!',
        'token' => $jwt,
        'expires_in' => JWT_TTL_SECONDS,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'avatar' => $user['avatar'],
            'role' => $user['role'],
        ],
    ]);
} catch (InvalidArgumentException $e) {
    Logger::auth('User login failed - invalid request');
    ResponseService::error($e->getMessage(), $e->getCode() ?: 400);
} catch (Throwable $e) {
    error_log('[Login] ' . $e->getMessage());
    ResponseService::error('Lỗi hệ thống. Vui lòng thử lại sau.', 500);
}
