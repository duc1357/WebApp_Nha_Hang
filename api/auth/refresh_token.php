<?php
// api/auth/refresh_token.php
// Endpoint: POST /api/auth/refresh_token.php
// Chức năng: Làm mới JWT token trong grace period 30 phút sau khi hết hạn
//
// Request body: { "token": "<jwt_token>" }
// Response: { "success": true, "token": "<new_jwt>", "expires_in": 604800 }

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/api/base.php';
require_once ROOT_PATH . '/api/services/jwt_service.php';

requireMethod('POST');

$data     = getJsonBody(required: true);
$oldToken = $data['token'] ?? '';

if (empty($oldToken)) {
    apiError('Token không được để trống', 400);
}

$newToken = JwtService::refresh($oldToken);

if (!$newToken) {
    apiError('Token đã hết hạn hoặc không hợp lệ. Vui lòng đăng nhập lại.', 401);
}

apiSuccess([
    'token'      => $newToken,
    'expires_in' => JWT_TTL_SECONDS,
], 'Token đã được làm mới');
