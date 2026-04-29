<?php
// api/admin/checkout_table.php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$data = json_decode(file_get_contents('php://input'), true);

$table_id = isset($data['table_id']) ? (int)$data['table_id'] : 0;
$payment_method = isset($data['payment_method']) ? trim($data['payment_method']) : 'cash';

if ($table_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Lỗi ID bàn.']);
    exit;
}

// 1. Tìm order pending của bàn
$sql = "SELECT id FROM orders WHERE table_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $table_id);
$stmt->execute();
$resOrder = $stmt->get_result();

if ($resOrder->num_rows === 0) {
    // Không có hoá đơn pending, nhưng vẫn trả bàn
    $upTable = $conn->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
    $upTable->bind_param("i", $table_id);
    $upTable->execute();
    $upTable->close();

    echo json_encode(['success' => true, 'message' => 'Bàn rỗng đã được trả.']);
    exit;
}

$order = $resOrder->fetch_assoc();
$order_id = $order['id'];
$stmt->close();

// 2. Chuyển order status = 'paid', update payment_method
$updOrder = $conn->prepare("UPDATE orders SET status = 'paid', payment_method = ? WHERE id = ?");
$updOrder->bind_param("si", $payment_method, $order_id);
if (!$updOrder->execute()) {
    echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật hoá đơn: ' . $updOrder->error]);
    exit;
}
$updOrder->close();

// 3. Trả bàn về 'available'
$upTable = $conn->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
$upTable->bind_param("i", $table_id);
if (!$upTable->execute()) {
    echo json_encode(['success' => false, 'message' => 'Lỗi trả bàn: ' . $upTable->error]);
    exit;
}
$upTable->close();

$date = isset($data['date']) ? $data['date'] : date('Y-m-d');
$completeB = $conn->prepare("UPDATE bookings SET status = 'completed' WHERE table_id = ? AND date = ? AND status = 'confirmed'");
$completeB->bind_param("is", $table_id, $date);
$completeB->execute();
$completeB->close();

echo json_encode(['success' => true, 'message' => 'Thanh toán & Trả bàn thành công!']);
$conn->close();
?>
