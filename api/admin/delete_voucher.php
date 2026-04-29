<?php
// api/admin/delete_voucher.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth_check_api.php'; // [2.3] Standardized
require_once ROOT_PATH . '/config/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Thiáº¿u ID']);
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
    echo json_encode(['success' => true, 'message' => 'ÄÃ£ xÃ³a vÄ©nh viá»…n mÃ£ giáº£m giÃ¡']);
} else {
    // Handle FK constraint violation if any
    if ($conn->errno == 1451) {
        echo json_encode(['success' => false, 'message' => 'KhÃ´ng thá»ƒ xÃ³a: MÃ£ nÃ y Ä‘ang Ä‘Æ°á»£c sá»­ dá»¥ng trong lá»‹ch sá»­ Ä‘Æ¡n hÃ ng.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lá»—i: ' . $conn->error]);
    }
}

$conn->close();
?>

