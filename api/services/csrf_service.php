<?php
// Ensure secure session is started
require_once __DIR__ . '/../../config/constants.php';

class CsrfService {
    // Generate Token
    public static function generateToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Verify Token
    public static function verifyToken($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    // Check Headers/Input for Token
    public static function validateRequest() {
        // Skip for GET requests or non-modifying methods if strictly needed, 
        // but typically all API calls modifying data should be checked.
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return true;
        }

        $headers = getallheaders();
        $token = $headers['X-CSRF-Token'] ?? ($_POST['csrf_token'] ?? '');
        
        // Also check JSON input if not found in headers
        if (!$token) {
            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['csrf_token'] ?? '';
        }

        if (!self::verifyToken($token)) {
            error_log("CSRF Fail: Session Token: " . ($_SESSION['csrf_token'] ?? 'NULL') . " | Received: " . $token);
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF Validation Failed']);
            exit;
        }
        return true;
    }
}
