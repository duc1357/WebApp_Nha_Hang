<?php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

// Determine input method
$inputData = [];

// 1. Try JSON input (default for toggle status)
$rawInput = file_get_contents("php://input");
$jsonInput = json_decode($rawInput, true);

if (!empty($jsonInput)) {
    $inputData = $jsonInput;
} else {
    // 2. Try $_POST (for FormData edits)
    $inputData = $_POST;
}

$id = $inputData['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID is required']);
    exit;
}

// Handle File Upload if present in $_FILES
$photoUrl = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['photo'];
    $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate type (simple check)
    
    $uploadDir = ROOT_PATH . '/photo/menu';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = 'menu_' . time() . '_' . rand(100,999) . '.' . $ext;
    $targetPath = $uploadDir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $photoUrl = 'photo/menu/' . $filename;
    }
}

// Prepare SQL Construction
$conn = getDbConnection();
$updateFields = [];
$types = "";
$params = [];

if (isset($inputData['name'])) {
    $updateFields[] = "name=?";
    $types .= "s";
    $params[] = $inputData['name'];
}
if (isset($inputData['description'])) {
    $updateFields[] = "description=?";
    $types .= "s";
    $params[] = $inputData['description'];
}
if (isset($inputData['price']) && $inputData['price'] !== '') {
    $updateFields[] = "price=?";
    $types .= "i";
    $params[] = (int)$inputData['price'];
}
// If photo was just uploaded, use update it. 
// OR if 'photo' string was passed (legacy URL), use that.
// Prioritize uploaded file.
if ($photoUrl) {
    $updateFields[] = "image_url=?";
    $types .= "s";
    $params[] = $photoUrl;
} elseif (isset($inputData['photo'])) {
    $updateFields[] = "image_url=?";
    $types .= "s";
    $params[] = $inputData['photo'];
}

if (isset($inputData['is_active'])) {
    $updateFields[] = "is_active=?";
    $types .= "i";
    $params[] = $inputData['is_active'];
}

if (empty($updateFields)) {
    echo json_encode(['success' => false, 'message' => 'No fields to update']);
    exit;
}

$sql = "UPDATE menu_items SET " . implode(", ", $updateFields) . " WHERE id=?";
$types .= "i";
$params[] = $id;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
