<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$response = ['success' => false, 'message' => ''];

// [SECURITY FIX] Kiểm tra đăng nhập – không tin user_id từ POST
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    $response['message'] = 'Vui lòng đăng nhập để thực hiện chức năng này';
    echo json_encode($response);
    exit;
}

// [SECURITY FIX] Lấy user_id từ SESSION, KHÔNG từ $_POST (tránh IDOR)
$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Vui lòng chọn file ảnh hợp lệ';
    echo json_encode($response);
    exit;
}

$file = $_FILES['avatar'];

// [2.4] Giới hạn kích thước: 2MB
$maxSize = 2 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    $response['message'] = 'Ảnh không được vượt quá 2MB';
    echo json_encode($response);
    exit;
}

// [2.4] Dùng finfo kiểm tra MIME type thực (không tin extension)
$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/jpg'  => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!array_key_exists($mime, $allowedMimes)) {
    $response['message'] = 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP)';
    echo json_encode($response);
    exit;
}

// Tạo thư mục nếu chưa có
$uploadDir = ROOT_PATH . '/photo/avatars';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// [2.4] Dùng safe extension từ MIME map (không từ tên file gốc)
$safeExt    = $allowedMimes[$mime];
$filename   = 'ua_' . $user_id . '_' . time() . '.' . $safeExt;
$targetPath = $uploadDir . '/' . $filename;
$dbPath = 'photo/avatars/' . $filename; // Path to save in DB

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    
    // Update DB
    $conn = getDbConnection();
    
    // Optional: Delete old avatar if needed (not implemented for simplicity)
    
    $sql = "UPDATE users SET avatar = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $dbPath, $user_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Cập nhật avatar thành công';
        $response['avatar_url'] = $dbPath;
    } else {
        $response['message'] = 'Lỗi cập nhật database: ' . $conn->error;
    }
    
    $stmt->close();
    $conn->close();
    
} else {
    $response['message'] = 'Lỗi lưu file ảnh';
}

echo json_encode($response);
exit;
