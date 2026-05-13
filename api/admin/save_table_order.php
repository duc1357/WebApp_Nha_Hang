<?php
// api/admin/save_table_order.php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$table_id = isset($data['table_id']) ? (int)$data['table_id'] : 0;
$booking_id = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;
$items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

if ($table_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID bàn không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = getDbConnection();
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("SELECT id, discount_amount FROM orders WHERE table_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $table_id);
    $stmt->execute();
    $resOrder = $stmt->get_result();

    $bookingDeposit = 0.0;

    if ($resOrder->num_rows > 0) {
        $order = $resOrder->fetch_assoc();
        $order_id = (int)$order['id'];
        $bookingDeposit = (float)($order['discount_amount'] ?? 0);
    } else {
        $orderUserId = null;
        $orderNote = '';

        if ($booking_id > 0) {
            $stmtBooking = $conn->prepare("SELECT id, user_id, deposit_amount FROM bookings WHERE id = ? AND table_id = ? AND status = 'confirmed' AND has_preorder = 1 FOR UPDATE");
            $stmtBooking->bind_param('ii', $booking_id, $table_id);
            $stmtBooking->execute();
            $booking = $stmtBooking->get_result()->fetch_assoc();
            $stmtBooking->close();

            if (!$booking) {
                throw new RuntimeException('Booking not found or not convertible');
            }

            $orderUserId = $booking['user_id'] !== null ? (int)$booking['user_id'] : null;
            $bookingDeposit = (float)$booking['deposit_amount'];
            $orderNote = 'Tu Dat Ban #' . $booking_id;
        }

        $stmtIns = $conn->prepare("INSERT INTO orders (user_id, total_amount, discount_amount, final_total, status, table_id, payment_method, note, created_at)
                                   VALUES (?, 0, ?, 0, 'pending', ?, 'cash', ?, NOW())");
        if (!$stmtIns) {
            throw new RuntimeException('Prepare order insert failed: ' . $conn->error);
        }
        $stmtIns->bind_param('idis', $orderUserId, $bookingDeposit, $table_id, $orderNote);
        if (!$stmtIns->execute()) {
            throw new RuntimeException('Order insert failed: ' . $stmtIns->error);
        }
        $order_id = (int)$stmtIns->insert_id;
        $stmtIns->close();
    }
    $stmt->close();

    if ($order_id <= 0) {
        throw new RuntimeException('Cannot resolve order id');
    }

    $stmtDel = $conn->prepare('DELETE FROM order_items WHERE order_id = ?');
    if (!$stmtDel) {
        throw new RuntimeException('Prepare order item delete failed: ' . $conn->error);
    }
    $stmtDel->bind_param('i', $order_id);
    if (!$stmtDel->execute()) {
        throw new RuntimeException('Order item delete failed: ' . $stmtDel->error);
    }
    $stmtDel->close();

    $itemQtyMap = [];
    foreach ($items as $item) {
        $menu_item_id = isset($item['menu_item_id']) ? (int)$item['menu_item_id'] : 0;
        $qty = isset($item['quantity']) ? (int)$item['quantity'] : 0;
        if ($menu_item_id > 0 && $qty > 0) {
            $itemQtyMap[$menu_item_id] = ($itemQtyMap[$menu_item_id] ?? 0) + $qty;
        }
    }

    $total = 0;
    if (!empty($itemQtyMap)) {
        $ids = array_keys($itemQtyMap);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $priceStmt = $conn->prepare("SELECT id, price FROM menu_items WHERE id IN ($placeholders) AND deleted_at IS NULL AND is_active = 1");
        if (!$priceStmt) {
            throw new RuntimeException('Prepare price lookup failed: ' . $conn->error);
        }
        $priceStmt->bind_param($types, ...$ids);
        if (!$priceStmt->execute()) {
            throw new RuntimeException('Price lookup failed: ' . $priceStmt->error);
        }
        $prices = $priceStmt->get_result();

        $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
        if (!$itemStmt) {
            throw new RuntimeException('Prepare order item insert failed: ' . $conn->error);
        }
        while ($row = $prices->fetch_assoc()) {
            $menu_item_id = (int)$row['id'];
            $qty = $itemQtyMap[$menu_item_id];
            $real_price = (int)$row['price'];
            $total += $real_price * $qty;
            $itemStmt->bind_param('iiii', $order_id, $menu_item_id, $qty, $real_price);
            if (!$itemStmt->execute()) {
                throw new RuntimeException('Order item insert failed: ' . $itemStmt->error);
            }
        }
        $itemStmt->close();
        $priceStmt->close();
    }

    $finalTotal = max(0, $total - $bookingDeposit);
    $upd = $conn->prepare('UPDATE orders SET total_amount = ?, discount_amount = ?, final_total = ? WHERE id = ?');
    if (!$upd) {
        throw new RuntimeException('Prepare order total update failed: ' . $conn->error);
    }
    $upd->bind_param('dddi', $total, $bookingDeposit, $finalTotal, $order_id);
    if (!$upd->execute()) {
        throw new RuntimeException('Order total update failed: ' . $upd->error);
    }
    $upd->close();

    if ($booking_id > 0) {
        $updBooking = $conn->prepare("UPDATE bookings SET status = 'arrived' WHERE id = ? AND status = 'confirmed'");
        if (!$updBooking) {
            throw new RuntimeException('Prepare booking update failed: ' . $conn->error);
        }
        $updBooking->bind_param('i', $booking_id);
        if (!$updBooking->execute()) {
            throw new RuntimeException('Booking update failed: ' . $updBooking->error);
        }
        $updBooking->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Cập nhật order thành công'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[AdminSaveTableOrder] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Không thể cập nhật order'], JSON_UNESCAPED_UNICODE);
}

$conn->close();
