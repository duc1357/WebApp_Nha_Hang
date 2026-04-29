<?php
// session_start(); // Handled by auth_check_api
require_once __DIR__ . '/auth_check_api.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$conn = getDbConnection();

// ĐỌC JSON TỪ FETCH
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'message' => 'Dữ liệu JSON không hợp lệ'
    ], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

$id     = (int)($data['id'] ?? 0);
$status = trim($data['status'] ?? '');

// CHO PHÉP CHỈ 2 TRẠNG THÁI NÀY
$allowed = ['pending', 'paid', 'cancelled', 'confirmed'];

if ($id <= 0 || !in_array($status, $allowed, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'ID hoặc trạng thái không hợp lệ'
    ], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

// UPDATE TRONG BẢNG orders
$stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Cập nhật trạng thái thành công',
            'id'      => $id,
            'status'  => $status
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy đơn để cập nhật'
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi MySQL: ' . $stmt->error
    ], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
