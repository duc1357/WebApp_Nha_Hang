<?php

class JwtService
{
    private const ISSUER = 'duong-bau-restaurant';
    private const MIN_SECRET_LENGTH = 32;

    private static function getSecret(): string
    {
        $secret = defined('JWT_SECRET') ? JWT_SECRET : (getenv('JWT_SECRET') ?: '');
        if (strlen($secret) < self::MIN_SECRET_LENGTH) {
            throw new RuntimeException('JWT_SECRET must be configured with at least 32 characters');
        }
        return $secret;
    }

    private static function getTtl(): int
    {
        return defined('JWT_TTL_SECONDS') ? (int)JWT_TTL_SECONDS : 604800;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $rem = strlen($data) % 4;
        $padded = $rem ? $data . str_repeat('=', 4 - $rem) : $data;
        return base64_decode(strtr($padded, '-_', '+/'));
    }

    public static function generate(array $extraClaims = [], ?int $ttl = null): string
    {
        $now = time();
        $ttl = $ttl ?? self::getTtl();

        $header = self::base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ]));

        $payload = self::base64UrlEncode(json_encode(array_merge([
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'iss' => self::ISSUER,
        ], $extraClaims)));

        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", self::getSecret(), true)
        );

        return "{$header}.{$payload}.{$signature}";
    }

    public static function verify(string $token): array|false
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;
        $expected = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", self::getSecret(), true)
        );

        if (!hash_equals($expected, $signature)) {
            return false;
        }

        $decoded = json_decode(self::base64UrlDecode($payload), true);
        if (!is_array($decoded)) {
            return false;
        }

        $now = time();
        if (isset($decoded['exp']) && $now > $decoded['exp']) {
            return false;
        }

        if (isset($decoded['nbf']) && $now < $decoded['nbf']) {
            return false;
        }

        if (($decoded['iss'] ?? '') !== self::ISSUER) {
            return false;
        }

        return $decoded;
    }

    public static function getBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        if (!$header && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? null;
        }

        if ($header && preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function requireToken(?string $requiredRole = null): array
    {
        require_once __DIR__ . '/response_service.php';

        $token = self::getBearerToken();
        if (!$token) {
            ResponseService::error('Authorization token not found', 401);
        }

        $payload = self::verify($token);
        if (!$payload) {
            ResponseService::error('Token is invalid or expired', 401);
        }

        if ($requiredRole && ($payload['role'] ?? '') !== $requiredRole) {
            ResponseService::error('Insufficient permissions', 403);
        }

        if (isset($payload['user_id'])) {
            require_once dirname(__DIR__, 2) . '/config/db.php';
            $conn = getDbConnection();
            $stmt = $conn->prepare("SELECT token_version FROM users WHERE id = ?");
            $stmt->bind_param("i", $payload['user_id']);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();
            $stmt->close();
            $conn->close();

            $tokenVersion = $payload['token_version'] ?? 1;
            if (!$user || (int)$user['token_version'] > (int)$tokenVersion) {
                ResponseService::error('Token has been revoked. Please login again.', 401);
            }
        }

        return $payload;
    }

    public static function refresh(string $oldToken): string|false
    {
        $parts = explode('.', $oldToken);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;

        $expected = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", self::getSecret(), true)
        );
        if (!hash_equals($expected, $signature)) {
            return false;
        }

        $decoded = json_decode(self::base64UrlDecode($payload), true);
        if (!is_array($decoded)) {
            return false;
        }

        if (($decoded['iss'] ?? '') !== self::ISSUER) {
            return false;
        }

        $gracePeriod = 30 * 60;
        if (isset($decoded['exp']) && time() > ((int)$decoded['exp'] + $gracePeriod)) {
            return false;
        }

        $customClaims = array_diff_key($decoded, array_flip(['iat', 'nbf', 'exp', 'iss']));
        return self::generate($customClaims);
    }
}
