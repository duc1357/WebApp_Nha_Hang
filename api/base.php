<?php
// api/base.php
// Base API Response Handler – Chuẩn hóa tất cả JSON responses
// Dùng: require_once ROOT_PATH . '/api/base.php';

/**
 * Gửi JSON response thành công và thoát.
 *
 * @param mixed  $data    Data trả về (array, object, hoặc null)
 * @param string $message Thông điệp hiển thị
 * @param int    $code    HTTP status code
 */
function apiSuccess($data = null, string $message = 'Thành công', int $code = 200): void {
    http_response_code($code);
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Gửi JSON response lỗi và thoát.
 *
 * @param string $message Thông điệp lỗi (không tiết lộ thông tin nhạy cảm)
 * @param int    $code    HTTP status code (400, 401, 403, 404, 429, 500...)
 * @param mixed  $errors  Chi tiết lỗi tuỳ chọn (dùng cho validation)
 */
function apiError(string $message, int $code = 400, $errors = null): void {
    http_response_code($code);
    $response = ['success' => false, 'message' => $message];
    if ($errors !== null) {
        $response['errors'] = $errors;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Yêu cầu method cụ thể, trả lỗi nếu không khớp.
 *
 * @param string|string[] $methods Một hoặc nhiều HTTP method cho phép
 */
function requireMethod(string|array $methods): void {
    $methods = array_map('strtoupper', (array) $methods);
    if (!in_array(strtoupper($_SERVER['REQUEST_METHOD'] ?? ''), $methods, true)) {
        apiError('Method Not Allowed', 405);
    }
}

/**
 * Đọc và parse JSON từ request body.
 *
 * @param  bool  $required Nếu true, thoát với lỗi khi không có dữ liệu
 * @return array
 */
function getJsonBody(bool $required = false): array {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if ($required && (empty($raw) || !is_array($data))) {
        apiError('Request body phải là JSON hợp lệ', 400);
    }
    return is_array($data) ? $data : [];
}

/**
 * Lấy giá trị từ array với fallback, đồng thời sanitize cơ bản.
 *
 * @param array  $data    Source array ($_POST, parsed JSON, etc.)
 * @param string $key     Key cần lấy
 * @param mixed  $default Giá trị mặc định nếu không có
 * @param string $type    'string' | 'int' | 'float' | 'bool' | 'email'
 */
function getParam(array $data, string $key, $default = null, string $type = 'string') {
    $val = $data[$key] ?? $default;
    if ($val === null || $val === $default) return $default;

    return match($type) {
        'int'    => (int) $val,
        'float'  => (float) $val,
        'bool'   => (bool) $val,
        'email'  => filter_var(trim($val), FILTER_VALIDATE_EMAIL) ?: null,
        default  => trim(htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8')),
    };
}

/**
 * Yêu cầu user đã đăng nhập (user hoặc admin).
 * Thoát với 401 nếu chưa xác thực.
 *
 * @param  string|null $role Nếu có, yêu cầu role cụ thể ('admin', 'user')
 * @return int         user_id hiện tại
 */
function requireAuth(?string $role = null): int {
    if (!isset($_SESSION['user_id'])) {
        apiError('Vui lòng đăng nhập để tiếp tục', 401);
    }
    if ($role !== null && ($_SESSION['role'] ?? '') !== $role) {
        apiError('Bạn không có quyền thực hiện thao tác này', 403);
    }
    return (int) $_SESSION['user_id'];
}
