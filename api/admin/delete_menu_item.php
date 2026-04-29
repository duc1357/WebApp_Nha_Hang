<?php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID is required']);
    exit;
}

$conn = getDbConnection();
$sql = "UPDATE menu_items SET deleted_at = NOW() WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Đã chuyển món vào thùng rác (Soft Delete)']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
