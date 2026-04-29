<?php
// api/admin/save_table_order.php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
$conn = getDbConnection();

$data = json_decode(file_get_contents('php://input'), true);

$table_id = isset($data['table_id']) ? (int)$data['table_id'] : 0;
$items = isset($data['items']) ? $data['items'] : [];

if ($table_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Lỗi ID bàn.']);
    exit;
}

// Tìm order pending
$sql = "SELECT id FROM orders WHERE table_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $table_id);
$stmt->execute();
$resOrder = $stmt->get_result();

$order_id = 0;
if ($resOrder->num_rows > 0) {
    // Đã có order
    $order = $resOrder->fetch_assoc();
    $order_id = $order['id'];
} else {
    // Tạo order mới
    // POS use user_id = null
    $sqlInsert = "INSERT INTO orders (user_id, total_amount, final_total, status, table_id, payment_method, note, created_at)
                  VALUES (NULL, 0, 0, 'pending', ?, 'cash', '', NOW())";
    $stmtIns = $conn->prepare($sqlInsert);
    // table_id is standard int based on database type but user_id is null, here we pass $table_id as string/int.
    // Ensure table_id column in orders is string, but if int, we pass int.
    // Earlier: "table_id: orderType === 'dine_in' ? tableNum : ''" (sent as string). Let's bind as string just in case, but int works.
    $table_id_str = (string)$table_id;
    $stmtIns->bind_param("s", $table_id_str);
    if ($stmtIns->execute()) {
        $order_id = $stmtIns->insert_id;
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi tạo order mới: ' . $stmtIns->error]);
        exit;
    }
    $stmtIns->close();
}
$stmt->close();

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Không thể xác định Order ID.']);
    exit;
}

// Xóa tất cả items cũ của order này
$delSql = "DELETE FROM order_items WHERE order_id = ?";
$stmtDel = $conn->prepare($delSql);
$stmtDel->bind_param("i", $order_id);
$stmtDel->execute();
$stmtDel->close();

if (empty($items)) {
    // Nếu trống (admin xóa hết món) -> update order total = 0
    $upd0 = $conn->prepare("UPDATE orders SET total_amount = 0, final_total = 0 WHERE id = ?");
    $upd0->bind_param("i", $order_id);
    $upd0->execute();
    $upd0->close();
    
    echo json_encode(['success' => true, 'message' => 'Đã cập nhật đơn hàng thành rỗng.']);
    exit;
}

// Thêm items mới và tính tổng
$total_calculated = 0;
$sql_item = "INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price) VALUES (?, ?, ?, ?)";
$stmt_item = $conn->prepare($sql_item);

$priceStmt = $conn->prepare("SELECT price FROM menu_items WHERE id = ?");

foreach ($items as $item) {
    $menu_item_id = (int)$item['menu_item_id'];
    $qty = (int)$item['quantity'];
    if ($menu_item_id <= 0 || $qty <= 0) continue;

    $priceStmt->bind_param("i", $menu_item_id);
    $priceStmt->execute();
    $resPrice = $priceStmt->get_result();
    
    if ($rowPrice = $resPrice->fetch_assoc()) {
        $real_price = (int)$rowPrice['price'];
        $total_calculated += ($real_price * $qty);
        
        $stmt_item->bind_param("iiii", $order_id, $menu_item_id, $qty, $real_price);
        $stmt_item->execute();
    }
}
$priceStmt->close();
$stmt_item->close();

// Update order total
$upd = $conn->prepare("UPDATE orders SET total_amount = ?, final_total = ? WHERE id = ?");
$upd->bind_param("ddi", $total_calculated, $total_calculated, $order_id);
$upd->execute();
$upd->close();

echo json_encode(['success' => true, 'message' => 'Cập nhật Order thành công.']);
$conn->close();
?>
