<?php
// api/admin/cancel_table_order.php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$data = json_decode(file_get_contents('php://input'), true);
$table_id = isset($data['table_id']) ? (int)$data['table_id'] : 0;

if ($table_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Lỗi ID bàn.']);
    exit;
}

// Tìm order đang pending
$sql = "SELECT id FROM orders WHERE table_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $table_id);
$stmt->execute();
$resOrder = $stmt->get_result();

if ($resOrder->num_rows > 0) {
    $order = $resOrder->fetch_assoc();
    $order_id = $order['id'];
    
    // Hủy order
    $updOrder = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
    $updOrder->bind_param("i", $order_id);
    $updOrder->execute();
    $updOrder->close();
}
$stmt->close();

// Chuyển bàn về trống (available) trong bảng tables (áp dụng cho trường hợp bật thủ công chưa order)
$updTable = $conn->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
$updTable->bind_param("i", $table_id);
$updTable->execute();
$updTable->close();

$date = isset($data['date']) ? $data['date'] : date('Y-m-d');
$cancelB = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE table_id = ? AND date = ? AND status = 'confirmed'");
$cancelB->bind_param("is", $table_id, $date);
$cancelB->execute();
$cancelB->close();

echo json_encode(['success' => true, 'message' => 'Hủy order và chuyển bàn về trống thành công.']);
$conn->close();
?>
