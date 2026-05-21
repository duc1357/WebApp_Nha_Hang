<?php
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';



if (!isset($_SESSION['user_id'])) {
    ResponseService::error('Unauthorized', 401);
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    ResponseService::error('Invalid Order ID', 422);
}

$conn = getDbConnection();

// Verify ownership: Check if this order belongs to the logged-in user
$checkStmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
$checkStmt->bind_param("ii", $order_id, $user_id);
$checkStmt->execute();
$resCheck = $checkStmt->get_result();

if ($resCheck->num_rows === 0) {
    ResponseService::error('Check order ownership failed', 403);
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

$stmt->close();
$conn->close();
ResponseService::success(['data' => $items]);
