<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$inputData = is_array($jsonInput) && !empty($jsonInput) ? $jsonInput : $_POST;

$id = isset($inputData['id']) ? (int)$inputData['id'] : 0;
if ($id <= 0) {
    ResponseService::json(['success' => false, 'message' => 'ID không hợp lệ'], 400);
    exit;
}

$photoUrl = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['photo'];
    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        ResponseService::json(['success' => false, 'message' => 'Ảnh không được vượt quá 2MB'], 422);
        exit;
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mimeType, $allowedMimes)) {
        ResponseService::json(['success' => false, 'message' => 'Chỉ chấp nhận ảnh JPG, PNG, WEBP'], 422);
        exit;
    }

    $uploadDir = ROOT_PATH . '/photo/menu';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'menu_' . bin2hex(random_bytes(12)) . '.' . $allowedMimes[$mimeType];
    $targetPath = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        ResponseService::json(['success' => false, 'message' => 'Không thể lưu ảnh'], 500);
        exit;
    }
    $photoUrl = 'photo/menu/' . $filename;
}

$updateFields = [];
$types = '';
$params = [];

if (isset($inputData['name'])) {
    $name = trim($inputData['name']);
    if ($name === '' || strlen($name) > 100) {
        ResponseService::json(['success' => false, 'message' => 'Tên món phải từ 1 đến 100 ký tự'], 422);
        exit;
    }
    $updateFields[] = 'name=?';
    $types .= 's';
    $params[] = $name;
}
if (isset($inputData['description'])) {
    $description = trim($inputData['description']);
    $updateFields[] = 'description=?';
    $types .= 's';
    $params[] = $description;
}
if (isset($inputData['price']) && $inputData['price'] !== '') {
    $price = (int)$inputData['price'];
    if ($price <= 0 || $price > 10000000) {
        ResponseService::json(['success' => false, 'message' => 'Giá không hợp lệ'], 422);
        exit;
    }
    $updateFields[] = 'price=?';
    $types .= 'i';
    $params[] = $price;
}
if ($photoUrl) {
    $updateFields[] = 'image_url=?';
    $types .= 's';
    $params[] = $photoUrl;
} elseif (isset($inputData['photo'])) {
    $legacyPhoto = trim($inputData['photo']);
    if (!preg_match('#^photo/[A-Za-z0-9._/\-]+$#', $legacyPhoto)) {
        ResponseService::json(['success' => false, 'message' => 'Đường dẫn ảnh không hợp lệ'], 422);
        exit;
    }
    $updateFields[] = 'image_url=?';
    $types .= 's';
    $params[] = $legacyPhoto;
}
if (isset($inputData['is_active'])) {
    $isActive = (int)$inputData['is_active'] === 1 ? 1 : 0;
    $updateFields[] = 'is_active=?';
    $types .= 'i';
    $params[] = $isActive;
}

if (empty($updateFields)) {
    ResponseService::json(['success' => false, 'message' => 'Không có dữ liệu cần cập nhật'], 400);
    exit;
}

$conn = getDbConnection();
$sql = 'UPDATE menu_items SET ' . implode(', ', $updateFields) . ' WHERE id=?';
$types .= 'i';
$params[] = $id;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    ResponseService::json(['success' => true, 'message' => 'Cập nhật thành công']);
} else {
    error_log('[AdminUpdateMenuItem] ' . $stmt->error);
    ResponseService::json(['success' => false, 'message' => 'Không thể cập nhật món'], 500);
}

$stmt->close();
$conn->close();
