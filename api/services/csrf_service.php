<?php
// api/services/csrf_service.php
require_once __DIR__ . '/../../config/constants.php';

class CsrfService {

    /**
     * Lấy CSRF token hiện tại, tạo mới nếu chưa có.
     */
    public static function generateToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Rotate token sau các hành động quan trọng (login, logout, privilege change).
     * Ngăn chặn tấn công session fixation và token reuse.
     */
    public static function rotateToken(): string {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    /**
     * Xác thực CSRF token từ request.
     */
    public static function verifyToken(string $token): bool {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Validate request method và CSRF token.
     * Gọi ở đầu tất cả API endpoints thay đổi dữ liệu.
     */
    public static function validateRequest(): bool {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return true;
        }

        // Ưu tiên: Header > POST field > JSON body
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');

        if (empty($token)) {
            $raw   = file_get_contents('php://input');
            $input = json_decode($raw, true);
            $token = $input['csrf_token'] ?? '';
        }

        if (!self::verifyToken($token)) {
            $allHeaders = function_exists('getallheaders') ? getallheaders() : [];
            error_log("CSRF Fail | IP: " . ($_SERVER['REMOTE_ADDR'] ?? '-')
                . " | Has session token: " . (isset($_SESSION['csrf_token']) ? 'yes' : 'no')
                . " | Has received token: " . (!empty($token) ? 'yes' : 'no')
                . " | SERVER: " . json_encode(array_keys($_SERVER))
                . " | HEADERS: " . json_encode($allHeaders));
            
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF Validation Failed']);
            exit;
        }

        return true;
    }
}
