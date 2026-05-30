<?php
// api/base.php
// Backwards-compatible API helper functions.

require_once __DIR__ . '/services/response_service.php';
require_once __DIR__ . '/services/request_service.php';
require_once __DIR__ . '/services/auth_state_service.php';

function apiSuccess($data = null, string $message = 'Thanh cong', int $code = 200): void {
    $payload = ['message' => $message];
    if ($data !== null) {
        $payload['data'] = $data;
    }
    ResponseService::success($payload, $code);
}

function apiError(string $message, int $code = 400, $errors = null): void {
    $extra = [];
    if ($errors !== null) {
        $extra['errors'] = $errors;
    }
    ResponseService::error($message, $code, $extra);
}

function requireMethod(string|array $methods): void {
    $methods = array_map('strtoupper', (array)$methods);
    if (!in_array(strtoupper($_SERVER['REQUEST_METHOD'] ?? ''), $methods, true)) {
        apiError('Method Not Allowed', 405);
    }
}

function getJsonBody(bool $required = false): array {
    try {
        return RequestService::json($required);
    } catch (InvalidArgumentException $e) {
        apiError($e->getMessage(), $e->getCode() ?: 400);
    }
}

function getParam(array $data, string $key, $default = null, string $type = 'string') {
    $val = $data[$key] ?? $default;
    if ($val === null || $val === $default) {
        return $default;
    }

    return match($type) {
        'int' => (int)$val,
        'float' => (float)$val,
        'bool' => (bool)$val,
        'email' => filter_var(trim((string)$val), FILTER_VALIDATE_EMAIL) ?: null,
        default => trim(htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8')),
    };
}

function requireAuth(?string $role = null): int {
    $user = AuthStateService::requireSession($role);
    return (int)$user['id'];
}
