<?php
// api/admin/delete_voucher.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu ID']);
    exit;
}

$conn = getDbConnection();

// Soft delete usually means just setting is_active = 0 or creating a deleted_at column
// Since 'vouchers' table currently has is_active, let's use that to deactivate.
// Or actually DELETE if no orders used it? safer to just Deactivate explicitly or DELETE record if unused.
// Let's implement DELETE record checking for usage.

// Hard Delete requested by user (removing usage check)
$sql = "DELETE FROM vouchers WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Đã xóa vĩnh viễn mã giảm giá']);
} else {
    // Handle FK constraint violation if any
    if ($conn->errno == 1451) {
        echo json_encode(['success' => false, 'message' => 'Không thể xóa: Mã này đang được sử dụng trong lịch sử đơn hàng.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $conn->error]);
    }
}

$conn->close();
?>
