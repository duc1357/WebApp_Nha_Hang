<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Missing booking ID']);
    exit;
}

$conn = getDbConnection();
$stmt = $conn->prepare("SELECT payment_status FROM bookings WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'payment_status' => $row['payment_status']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
}

$stmt->close();
$conn->close();
?>
