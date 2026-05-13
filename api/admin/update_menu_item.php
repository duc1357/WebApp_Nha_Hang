<?php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$inputData = is_array($jsonInput) && !empty($jsonInput) ? $jsonInput : $_POST;

$id = isset($inputData['id']) ? (int)$inputData['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$photoUrl = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['photo'];
    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Ảnh không được vượt quá 2MB'], JSON_UNESCAPED_UNICODE);
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
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận ảnh JPG, PNG, WEBP'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $uploadDir = ROOT_PATH . '/photo/menu';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'menu_' . bin2hex(random_bytes(12)) . '.' . $allowedMimes[$mimeType];
    $targetPath = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Không thể lưu ảnh'], JSON_UNESCAPED_UNICODE);
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
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Tên món phải từ 1 đến 100 ký tự'], JSON_UNESCAPED_UNICODE);
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
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Giá không hợp lệ'], JSON_UNESCAPED_UNICODE);
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
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Đường dẫn ảnh không hợp lệ'], JSON_UNESCAPED_UNICODE);
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
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Không có dữ liệu cần cập nhật'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = getDbConnection();
$sql = 'UPDATE menu_items SET ' . implode(', ', $updateFields) . ' WHERE id=?';
$types .= 'i';
$params[] = $id;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Cập nhật thành công'], JSON_UNESCAPED_UNICODE);
} else {
    error_log('[AdminUpdateMenuItem] ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Không thể cập nhật món'], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
