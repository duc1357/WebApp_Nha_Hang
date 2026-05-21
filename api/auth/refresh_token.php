<?php
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/request_service.php';
require_once ROOT_PATH . '/api/services/validation_service.php';
require_once ROOT_PATH . '/api/services/jwt_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseService::error('Method Not Allowed', 405);
}

try {
    $data = RequestService::json(true);
    $oldToken = ValidationService::requiredString($data, 'token', 'Token khong duoc de trong');
    $newToken = JwtService::refresh($oldToken);

    if (!$newToken) {
        ResponseService::error('Token da het han hoac khong hop le. Vui long dang nhap lai.', 401);
    }

    ResponseService::success([
        'message' => 'Token da duoc lam moi',
        'token' => $newToken,
        'expires_in' => JWT_TTL_SECONDS,
    ]);
} catch (InvalidArgumentException $e) {
    ResponseService::error($e->getMessage(), $e->getCode() ?: 400);
} catch (Throwable $e) {
    error_log('[RefreshToken] ' . $e->getMessage());
    ResponseService::error('Loi he thong. Vui long thu lai sau.', 500);
}
