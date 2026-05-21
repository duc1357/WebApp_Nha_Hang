<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
// [2.3] Dùng auth_check_api.php chuẩn hóa (đã include constants.php + session check)
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();
require_once ROOT_PATH . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseService::json(['success' => false, 'message' => 'Invalid Request Method']);
    exit;
}

// Sanitize và validate input
$name        = trim(htmlspecialchars($_POST['name']        ?? '', ENT_QUOTES, 'UTF-8'));
$price       = (int) ($_POST['price'] ?? 0);
$description = trim(htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8'));
$photoUrl    = 'photo/default-food.png';

if (strlen($name) < 1 || strlen($name) > 100) {
    ResponseService::json(['success' => false, 'message' => 'Tên món phải từ 1-100 ký tự']);
    exit;
}
if ($price <= 0 || $price > 10_000_000) {
    ResponseService::json(['success' => false, 'message' => 'Giá không hợp lệ']);
    exit;
}

// [2.4] File Upload Hardening
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['photo'];

    // Giới hạn kích thước: 2MB
    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        ResponseService::json(['success' => false, 'message' => 'Ảnh không được vượt quá 2MB']);
        exit;
    }

    // Dùng finfo kiểm tra MIME type thực của file (không tin extension)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Map MIME → extension an toàn (không dùng extension từ tên file gốc)
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!array_key_exists($mimeType, $allowedMimes)) {
        ResponseService::json(['success' => false, 'message' => 'Chỉ chấp nhận ảnh JPG, PNG, WEBP']);
        exit;
    }

    $safeExt   = $allowedMimes[$mimeType];
    $uploadDir = ROOT_PATH . '/photo/menu';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Tên file duy nhất dùng uniqid (không đoán được)
    $filename   = 'menu_' . uniqid('', true) . '.' . $safeExt;
    $targetPath = $uploadDir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $photoUrl = 'photo/menu/' . $filename;
    } else {
        ResponseService::json(['success' => false, 'message' => 'Lỗi lưu file ảnh']);
        exit;
    }
}

$conn = getDbConnection();
$sql = "INSERT INTO menu_items (name, description, price, image_url, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssis", $name, $description, $price, $photoUrl);

if ($stmt->execute()) {
    ResponseService::json(['success' => true, 'message' => 'Thêm món thành công', 'data' => ['id' => $conn->insert_id, 'photo' => $photoUrl]]);
} else {
    error_log('[AdminAddMenuItem] ' . $stmt->error);
    ResponseService::json(['success' => false, 'message' => 'Không thể thêm món'], 500);
}

$stmt->close();
$conn->close();
