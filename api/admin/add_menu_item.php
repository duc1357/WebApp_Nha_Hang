<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

// Handle POST request
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request Method']);
    exit;
}

// Check if using FormData (files) or fallback to JSON (legacy support if needed, but we prioritize FormData)
$name = $_POST['name'] ?? '';
$price = $_POST['price'] ?? 0;
$description = $_POST['description'] ?? '';
$photoUrl = ''; // Default if no file uploaded

if (!$name || !$price) {
    echo json_encode(['success' => false, 'message' => 'Tên và giá là bắt buộc']);
    exit;
}

// Handle File Upload
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['photo'];
    $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate type (simple check)
    // For stricter check use finfo
    
    $uploadDir = ROOT_PATH . '/photo/menu';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique name: menu_TIMESTAMP.ext
    $filename = 'menu_' . time() . '_' . rand(100,999) . '.' . $ext;
    $targetPath = $uploadDir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $photoUrl = 'photo/menu/' . $filename;
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi lưu file ảnh']);
        exit;
    }
} else {
    // If user provided a URL string fallback (optional, from old logic)
    // or just leave empty
    $photoUrl = $_POST['photo'] ?? 'photo/default-food.png'; 
}

$conn = getDbConnection();
$sql = "INSERT INTO menu_items (name, description, price, image_url, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssis", $name, $description, $price, $photoUrl);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Thêm món thành công', 'data' => ['id' => $conn->insert_id, 'photo' => $photoUrl]]);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi thêm món: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
