<?php
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/payment_state_service.php';

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$userId) {
    ResponseService::error('Unauthorized', 401);
}

if ($bookingId <= 0) {
    ResponseService::error('Missing booking ID', 422);
}

$conn = getDbConnection();
$stmt = $conn->prepare("SELECT payment_status FROM bookings WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $bookingId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $paymentStatus = (string)$row['payment_status'];
    if (!in_array($paymentStatus, PaymentStateService::bookingPaymentStatuses(), true)) {
        $stmt->close();
        $conn->close();
        ResponseService::error('Invalid booking payment status', 500);
    }

    $stmt->close();
    $conn->close();
    ResponseService::success([
        'payment_status' => $paymentStatus,
        'is_paid' => PaymentStateService::isPaidBookingPaymentStatus($paymentStatus),
    ]);
}

$stmt->close();
$conn->close();
ResponseService::error('Booking not found', 404);
