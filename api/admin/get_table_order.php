<?php
// api/admin/get_table_order.php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

if ($table_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Lỗi ID bàn.']);
    exit;
}

// Tìm order đang "pending" của bàn này
$sql = "SELECT id, total_amount, final_total, note FROM orders WHERE table_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $table_id);
$stmt->execute();
$resOrder = $stmt->get_result();

if ($resOrder->num_rows > 0) {
    $order = $resOrder->fetch_assoc();
    $order_id = $order['id'];

    // Lấy chi tiết order_items
    $sqlItems = "
        SELECT oi.id as order_item_id, oi.menu_item_id, oi.quantity, oi.unit_price, m.name
        FROM order_items oi
        JOIN menu_items m ON oi.menu_item_id = m.id
        WHERE oi.order_id = ?
    ";
    $stmtItems = $conn->prepare($sqlItems);
    $stmtItems->bind_param("i", $order_id);
    $stmtItems->execute();
    $resItems = $stmtItems->get_result();
    
    $items = [];
    while ($item = $resItems->fetch_assoc()) {
        $items[] = $item;
    }
    $stmtItems->close();

    $order['items'] = $items;
    echo json_encode(['success' => true, 'order' => $order]);
} else {
    // Không có order pending. Kiểm tra xem có booking nào có preorder không.
    $today = date('Y-m-d');
    $sqlB = "SELECT id, user_id, total_amount, deposit_amount FROM bookings 
             WHERE table_id = ? AND date = ? AND status = 'confirmed' AND has_preorder = 1 
             ORDER BY time ASC LIMIT 1";
    $stmtB = $conn->prepare($sqlB);
    $stmtB->bind_param("is", $table_id, $today);
    $stmtB->execute();
    $resB = $stmtB->get_result();

    if ($resB->num_rows > 0) {
        $booking = $resB->fetch_assoc();
        
        $conn->begin_transaction();
        try {
            // Chuyển booking thành order pending
            $b_id = $booking['id'];
            $u_id = $booking['user_id'];
            $t_amount = (float)$booking['total_amount'];
            $d_amount = (float)$booking['deposit_amount'];
            $f_total = $t_amount - $d_amount;
            $note = "Từ Đặt Bàn #" . $b_id;

            $sqlIns = "INSERT INTO orders (user_id, table_id, total_amount, discount_amount, final_total, status, payment_method, note) 
                       VALUES (?, ?, ?, ?, ?, 'pending', 'cash', ?)";
            $stmtIns = $conn->prepare($sqlIns);
            $stmtIns->bind_param("iiddds", $u_id, $table_id, $t_amount, $d_amount, $f_total, $note);
            $stmtIns->execute();
            $new_order_id = $stmtIns->insert_id;
            $stmtIns->close();

            // Chép các món ăn từ booking_items
            $sqlItemsB = "SELECT menu_item_id, quantity, unit_price FROM booking_items WHERE booking_id = ?";
            $stmtIB = $conn->prepare($sqlItemsB);
            $stmtIB->bind_param("i", $b_id);
            $stmtIB->execute();
            $resItemsB = $stmtIB->get_result();
            
            $itemsObj = [];
            $insItem = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
            
            while ($bi = $resItemsB->fetch_assoc()) {
                $insItem->bind_param("iiid", $new_order_id, $bi['menu_item_id'], $bi['quantity'], $bi['unit_price']);
                $insItem->execute();
                
                // Fetch menu item name for the return json
                $nStmt = $conn->prepare("SELECT name FROM menu_items WHERE id = ?");
                $nStmt->bind_param("i", $bi['menu_item_id']);
                $nStmt->execute();
                $nr = $nStmt->get_result()->fetch_assoc();
                $name = $nr ? $nr['name'] : 'Món ăn';
                $nStmt->close();

                $itemsObj[] = [
                    'order_item_id' => $insItem->insert_id,
                    'menu_item_id' => $bi['menu_item_id'],
                    'quantity' => $bi['quantity'],
                    'unit_price' => $bi['unit_price'],
                    'name' => $name
                ];
            }
            $stmtIB->close();
            $insItem->close();

            // Cập nhật booking_status thành arrived
            $conn->query("UPDATE bookings SET status = 'arrived' WHERE id = " . $b_id);

            $conn->commit();

            $newOrder = [
                'id' => $new_order_id,
                'total_amount' => $t_amount,
                'final_total' => $f_total,
                'note' => $note,
                'items' => $itemsObj
            ];

            echo json_encode(['success' => true, 'order' => $newOrder]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi khởi tạo Order từ Booking']);
        }

    } else {
        // Hoàn toàn Không có order
        echo json_encode(['success' => true, 'order' => null]);
    }
    $stmtB->close();
}

$stmt->close();
$conn->close();
?>
