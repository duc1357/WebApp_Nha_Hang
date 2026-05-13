<?php
// api/services/jwt_service.php
// JSON Web Token Service – Không cần thư viện bên ngoài
//
// Mục đích:
//   - Cung cấp JWT authentication cho API (song song với session hiện tại)
//   - Chuẩn bị nền tảng cho mobile app hoặc third-party integrations
//   - Thuật toán: HMAC-SHA256 (HS256) – chuẩn công nghiệp, không cần RSA key pair
//
// Cách dùng:
//   $token = JwtService::generate(['user_id' => 1, 'role' => 'user']);
//   $payload = JwtService::verify($token); // false nếu invalid/expired

class JwtService
{
    // Secret key lấy từ .env (đặt trong env_loader.php)
    // Đổi JWT_SECRET thành chuỗi random dài ít nhất 32 ký tự
    private static function getSecret(): string
    {
        $secret = defined('JWT_SECRET') ? JWT_SECRET : (getenv('JWT_SECRET') ?: '');
        if (empty($secret)) {
            throw new RuntimeException('JWT_SECRET chưa được cấu hình trong .env');
        }
        return $secret;
    }

    // TTL mặc định: 7 ngày (configurable qua JWT_TTL_SECONDS trong .env)
    private static function getTtl(): int
    {
        return defined('JWT_TTL_SECONDS')
            ? (int) JWT_TTL_SECONDS
            : 604800; // 7 days
    }

    /* =========================================
       CORE: ENCODE / DECODE
       ========================================= */

    /** Base64Url encode (RFC 4648 §5 – không padding) */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** Base64Url decode */
    private static function base64UrlDecode(string $data): string
    {
        $rem     = strlen($data) % 4;
        $padded  = $rem ? $data . str_repeat('=', 4 - $rem) : $data;
        return base64_decode(strtr($padded, '-_', '+/'));
    }

    /* =========================================
       PUBLIC API
       ========================================= */

    /**
     * Tạo JWT token.
     *
     * @param  array $extraClaims  Thông tin thêm vào payload (vd: user_id, role)
     * @param  int|null $ttl      Thời gian sống tính bằng giây (null = mặc định)
     * @return string             JWT token string
     */
    public static function generate(array $extraClaims = [], ?int $ttl = null): string
    {
        $now = time();
        $ttl = $ttl ?? self::getTtl();

        // Header
        $header = self::base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ]));

        // Payload: Standard claims + custom claims
        $payload = self::base64UrlEncode(json_encode(array_merge([
            'iat' => $now,          // Issued At
            'nbf' => $now,          // Not Before
            'exp' => $now + $ttl,   // Expiration
            'iss' => 'duong-bau-restaurant', // Issuer
        ], $extraClaims)));

        // Signature: HMAC-SHA256
        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", self::getSecret(), true)
        );

        return "{$header}.{$payload}.{$signature}";
    }

    /**
     * Xác minh và decode JWT token.
     *
     * @param  string $token  JWT token cần verify
     * @return array|false    Payload nếu hợp lệ, false nếu invalid/expired
     */
    public static function verify(string $token): array|false
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;

        [$header, $payload, $signature] = $parts;

        // Kiểm tra signature
        $expected = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", self::getSecret(), true)
        );

        // hash_equals ngăn timing attack
        if (!hash_equals($expected, $signature)) return false;

        // Decode payload
        $decoded = json_decode(self::base64UrlDecode($payload), true);
        if (!is_array($decoded)) return false;

        $now = time();

        // Kiểm tra expiry
        if (isset($decoded['exp']) && $now > $decoded['exp']) return false;

        // Kiểm tra not-before
        if (isset($decoded['nbf']) && $now < $decoded['nbf']) return false;

        return $decoded;
    }

    /**
     * Lấy token từ Authorization header (Bearer token).
     * Dùng trong API endpoints cần xác thực JWT.
     *
     * @return string|null  Token string hoặc null nếu không tìm thấy
     */
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

    /**
     * Middleware helper: Yêu cầu JWT hợp lệ, trả lỗi 401 nếu không.
     * Dùng thay cho requireAuth() khi muốn xác thực bằng token thay vì session.
     *
     * @param  string|null $requiredRole  Role cần có (null = bất kỳ role nào)
     * @return array                      JWT payload
     */
    public static function requireToken(?string $requiredRole = null): array
    {
        $token = self::getBearerToken();
        if (!$token) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authorization token không tìm thấy'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $payload = self::verify($token);
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token không hợp lệ hoặc đã hết hạn'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($requiredRole && ($payload['role'] ?? '') !== $requiredRole) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Không đủ quyền truy cập'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Kiểm tra token_version nếu có user_id
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
            if (!$user || $user['token_version'] > $tokenVersion) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Token đã bị thu hồi. Vui lòng đăng nhập lại.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        return $payload;
    }

    /**
     * Refresh token: tạo token mới với claims giữ nguyên nhưng expiry reset.
     * Gọi từ endpoint /api/auth/refresh_token.php
     *
     * @param  string $oldToken  Token cũ (có thể đã hết hạn trong 30 phút)
     * @return string|false      Token mới hoặc false nếu quá hạn > 30 phút
     */
    public static function refresh(string $oldToken): string|false
    {
        $parts = explode('.', $oldToken);
        if (count($parts) !== 3) return false;

        [$header, $payload, $signature] = $parts;

        $expected = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", self::getSecret(), true)
        );
        if (!hash_equals($expected, $signature)) return false;

        $decoded = json_decode(self::base64UrlDecode($payload), true);
        if (!is_array($decoded)) return false;

        // Cho phép refresh trong vòng 30 phút sau khi hết hạn (grace period)
        $gracePeriod = 30 * 60;
        if (isset($decoded['exp']) && time() > ($decoded['exp'] + $gracePeriod)) {
            return false; // Quá hạn refresh
        }

        // Tạo token mới, loại bỏ các standard claims cũ
        $customClaims = array_diff_key($decoded, array_flip(['iat', 'nbf', 'exp', 'iss']));
        return self::generate($customClaims);
    }
}
