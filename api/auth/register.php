<?php
ob_start(); // Start buffer to catch warnings/errors
error_reporting(0);
ini_set('display_errors', 0);
header("Content-Type: application/json; charset=utf-8");

try {
    // ĐƯỜNG DẪN TUYỆT ĐỐI
    require_once __DIR__ . '/../../config/constants.php';
    require_once ROOT_PATH . '/config/db.php';
    require_once __DIR__ . '/../../api/services/csrf_service.php';
    require_once __DIR__ . '/../../api/services/rate_limit_service.php';

    // Rate Limit: 10 registrations / 60s
    if (!RateLimitService::check('register', 10, 60)) {
        throw new Exception("Thao tác quá nhanh. Vui lòng đợi.", 429);
    }

    CsrfService::validateRequest();

    $conn = getDbConnection();
    if ($conn->connect_error) {
        throw new Exception("Lỗi kết nối database: " . $conn->connect_error);
    }

    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!$data) {
        // Fallback for form data if needed, or just error
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
    } else {
        $name     = trim($data["name"] ?? "");
        $phone    = trim($data["phone"] ?? "");
        $email    = trim($data["email"] ?? "");
        $password = trim($data["password"] ?? "");
    }

    if ($name === "" || $phone === "" || $password === "") {
        throw new Exception("Vui lòng điền đủ Họ tên, SĐT và Mật khẩu.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         throw new Exception("Email không hợp lệ");
    }

    if (!preg_match('/^0[0-9]{9}$/', $phone)) {
         throw new Exception("Số điện thoại không hợp lệ (10 số, bắt đầu bằng 0)");
    }
    
    // Sanitize Name
    $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

    // BẢO MẬT: MÃ HÓA MẬT KHẨU
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare(
        "INSERT INTO users (name, phone, email, password, role) 
         VALUES (?, ?, ?, ?, 'customer')"
    );

    $stmt->bind_param("ssss", $name, $phone, $email, $hashed_password);

    if ($stmt->execute()) {
        ob_clean(); // Clean any noise
        echo json_encode([
            "success" => true,
            "message" => "Đăng ký thành công!"
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Check for duplicate entry error
        if ($conn->errno === 1062) {
            throw new Exception("Số điện thoại hoặc Email đã tồn tại.");
        } else {
            error_log('[Register] Insert failed: ' . $stmt->error);
            throw new Exception("Lỗi hệ thống. Vui lòng thử lại sau.");
        }
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    ob_clean(); // Clean buffer
    $code = $e->getCode() ?: 500;
    if ($code > 599 || $code < 100) $code = 500; // Sanitize status code
    http_response_code($code);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
