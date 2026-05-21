<?php
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';

// SEC-02: Validate CSRF cho upload (mutating request)
CsrfService::validateRequest();

// Kiểm tra đăng nhập – không tin user_id từ POST
if (!isset($_SESSION['user_id'])) {
    ResponseService::error('Vui lòng đăng nhập để thực hiện chức năng này', 401);
}

// Lấy user_id từ SESSION, KHÔNG từ $_POST (tránh IDOR)
$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseService::error('Method Not Allowed', 405);
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    ResponseService::error('Vui lòng chọn file ảnh hợp lệ', 400);
}

$file = $_FILES['avatar'];

// Giới hạn kích thước: 2MB
$maxSize = 2 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    ResponseService::error('Ảnh không được vượt quá 2MB', 400);
}

// Dùng finfo kiểm tra MIME type thực (không tin extension)
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
    ResponseService::error('Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP)', 400);
}

// Tạo thư mục nếu chưa có
$uploadDir = ROOT_PATH . '/photo/avatars';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Dùng safe extension từ MIME map (không từ tên file gốc)
$safeExt    = $allowedMimes[$mime];
$filename   = 'ua_' . $user_id . '_' . time() . '.' . $safeExt;
$targetPath = $uploadDir . '/' . $filename;
$dbPath     = 'photo/avatars/' . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $conn = getDbConnection();

    // Lấy avatar cũ để xóa file (optional cleanup)
    $oldStmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
    $oldStmt->bind_param("i", $user_id);
    $oldStmt->execute();
    $oldRow = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();

    // Xóa avatar cũ nếu tồn tại
    if (!empty($oldRow['avatar'])) {
        $oldFilePath = ROOT_PATH . '/' . $oldRow['avatar'];
        if (file_exists($oldFilePath)) {
            @unlink($oldFilePath);
        }
    }

    // Cập nhật DB
    $sql  = "UPDATE users SET avatar = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $dbPath, $user_id);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        ResponseService::success([
            'message' => 'Cập nhật avatar thành công',
            'avatar_url' => $dbPath,
        ]);
    } else {
        // CODE-06: Không lộ error nội bộ
        error_log('[Avatar] DB update failed: ' . $conn->error);
        $stmt->close();
        $conn->close();
        ResponseService::error('Lỗi cập nhật. Vui lòng thử lại.', 500);
    }
} else {
    ResponseService::error('Lỗi lưu file ảnh', 500);
}
