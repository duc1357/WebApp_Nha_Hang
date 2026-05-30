<?php

class AuthStateService
{
    public static function validateSession(?string $requiredRole = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Vui lòng đăng nhập để tiếp tục.',
            ];
        }

        require_once dirname(__DIR__, 2) . '/config/db.php';
        $conn = getDbConnection();
        $userId = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare('SELECT id, role, token_version, deleted_at FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();

        if (!$user || !empty($user['deleted_at'])) {
            self::clearSession();
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Phiên đăng nhập không hợp lệ. Vui lòng đăng nhập lại.',
            ];
        }

        $dbRole = (string)$user['role'];
        if ($requiredRole !== null && $dbRole !== $requiredRole) {
            self::clearSession();
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Bạn không có quyền thực hiện thao tác này.',
            ];
        }

        $dbTokenVersion = (int)($user['token_version'] ?? 0);
        if (!isset($_SESSION['token_version']) || (int)$_SESSION['token_version'] !== $dbTokenVersion) {
            self::clearSession();
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
            ];
        }

        $_SESSION['role'] = $dbRole;
        $_SESSION['token_version'] = $dbTokenVersion;

        return [
            'ok' => true,
            'user' => [
                'id' => (int)$user['id'],
                'role' => $dbRole,
                'token_version' => $dbTokenVersion,
            ],
        ];
    }

    public static function requireSession(?string $requiredRole = null): array
    {
        require_once __DIR__ . '/response_service.php';

        $result = self::validateSession($requiredRole);
        if (!$result['ok']) {
            ResponseService::error($result['message'], (int)$result['status']);
        }

        return $result['user'];
    }

    public static function syncCurrentSessionVersion(int $userId, int $tokenVersion, string $role): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ((int)($_SESSION['user_id'] ?? 0) !== $userId) {
            return;
        }

        $_SESSION['role'] = $role;
        $_SESSION['token_version'] = $tokenVersion;
    }

    public static function clearSession(): void
    {
        unset($_SESSION['user_id'], $_SESSION['name'], $_SESSION['role'], $_SESSION['token_version']);
    }
}
