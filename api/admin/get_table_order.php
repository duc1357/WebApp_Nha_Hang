<?php
// api/admin/get_table_order.php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';

$conn = getDbConnection();
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

if ($table_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID ban khong hop le.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("SELECT id, total_amount, final_total, note FROM orders WHERE table_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $table_id);
$stmt->execute();
$resOrder = $stmt->get_result();

if ($order = $resOrder->fetch_assoc()) {
    $order_id = (int)$order['id'];
    $stmtItems = $conn->prepare("
        SELECT oi.id as order_item_id, oi.menu_item_id, oi.quantity, oi.unit_price, m.name
        FROM order_items oi
        JOIN menu_items m ON oi.menu_item_id = m.id
        WHERE oi.order_id = ?
    ");
    $stmtItems->bind_param("i", $order_id);
    $stmtItems->execute();
    $resItems = $stmtItems->get_result();

    $items = [];
    while ($item = $resItems->fetch_assoc()) {
        $items[] = $item;
    }
    $stmtItems->close();

    $order['items'] = $items;
    echo json_encode(['success' => true, 'order' => $order, 'source' => 'order'], JSON_UNESCAPED_UNICODE);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

$today = date('Y-m-d');
$stmtBooking = $conn->prepare("
    SELECT id, user_id, total_amount, deposit_amount
    FROM bookings
    WHERE table_id = ?
      AND date = ?
      AND status = 'confirmed'
      AND has_preorder = 1
    ORDER BY time ASC
    LIMIT 1
");
$stmtBooking->bind_param("is", $table_id, $today);
$stmtBooking->execute();
$resBooking = $stmtBooking->get_result();

if ($booking = $resBooking->fetch_assoc()) {
    $booking_id = (int)$booking['id'];
    $stmtItems = $conn->prepare("
        SELECT bi.menu_item_id, bi.quantity, bi.unit_price, m.name
        FROM booking_items bi
        JOIN menu_items m ON bi.menu_item_id = m.id
        WHERE bi.booking_id = ?
    ");
    $stmtItems->bind_param("i", $booking_id);
    $stmtItems->execute();
    $resItems = $stmtItems->get_result();

    $items = [];
    while ($item = $resItems->fetch_assoc()) {
        $items[] = [
            'order_item_id' => null,
            'menu_item_id' => $item['menu_item_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'name' => $item['name'],
        ];
    }
    $stmtItems->close();

    echo json_encode([
        'success' => true,
        'order' => [
            'id' => null,
            'booking_id' => $booking_id,
            'total_amount' => (float)$booking['total_amount'],
            'final_total' => (float)$booking['total_amount'] - (float)$booking['deposit_amount'],
            'note' => 'Tu Dat Ban #' . $booking_id,
            'items' => $items,
        ],
        'source' => 'booking',
    ], JSON_UNESCAPED_UNICODE);
    $stmtBooking->close();
    $conn->close();
    exit;
}

$stmtBooking->close();
$conn->close();
echo json_encode(['success' => true, 'order' => null], JSON_UNESCAPED_UNICODE);
