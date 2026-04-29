<?php
// api/admin/update_table_status.php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$data = json_decode(file_get_contents('php://input'), true);

$id = isset($data['id']) ? (int)$data['id'] : 0;
$status = isset($data['status']) ? trim($data['status']) : '';

if ($id <= 0 || !in_array($status, ['available', 'occupied'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

$sql = "UPDATE tables SET status = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thành công']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
