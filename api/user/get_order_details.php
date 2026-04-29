<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';



if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Order ID']);
    exit;
}

$conn = getDbConnection();

// Verify ownership: Check if this order belongs to the logged-in user
$checkStmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
$checkStmt->bind_param("ii", $order_id, $user_id);
$checkStmt->execute();
$resCheck = $checkStmt->get_result();

if ($resCheck->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Check order ownership failed']);
    exit;
}
$checkStmt->close();

// Fetch details
// Join with menu_items to get name and image
$sql = "SELECT oi.quantity, oi.unit_price, m.name, m.image_url as image 
        FROM order_items oi 
        JOIN menu_items m ON oi.menu_item_id = m.id 
        WHERE oi.order_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

echo json_encode(['success' => true, 'data' => $items]);

$stmt->close();
$conn->close();
