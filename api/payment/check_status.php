<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Missing order ID']);
    exit;
}

$conn = getDbConnection();

$stmt = $conn->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'status' => $row['status'] // Will be 'paid' if webhook succeeded
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
}

$stmt->close();
$conn->close();
?>
