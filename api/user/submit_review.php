<?php
// api/user/submit_review.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$conn = getDbConnection();

// Check Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để đánh giá']);
    exit;
}
$userId = $_SESSION['user_id'];

$data = json_decode(file_get_contents("php://input"), true);
// $userId = $data['user_id'] ?? 0; // REMOVE INSECURE INPUT
$orderId = $data['order_id'] ?? 0;
$rating = $data['rating'] ?? 0;
$comment = $data['comment'] ?? '';

if (!$orderId || !$rating) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin bắt buộc']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Đánh giá phải từ 1 đến 5 sao']);
    exit;
}

$conn = getDbConnection();

// 1. Check ownership and status
$checkSql = "SELECT id, status FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($checkSql);
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Đơn hàng không tồn tại hoặc không thuộc về bạn']);
    exit;
}

$order = $result->fetch_assoc();
if ($order['status'] !== 'paid') { // Assuming 'paid' means completed/eligible for review
    echo json_encode(['success' => false, 'message' => 'Bạn chỉ có thể đánh giá đơn hàng đã hoàn thành (đã thanh toán)']);
    exit;
}

// 2. Check if already reviewed (unique constraint handles this, but nicer message here)
$checkReview = "SELECT id FROM reviews WHERE order_id = ?";
$stmtR = $conn->prepare($checkReview);
$stmtR->bind_param("i", $orderId);
$stmtR->execute();
if ($stmtR->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Bạn đã đánh giá đơn hàng này rồi']);
    exit;
}

// 3. Insert Review
$insertSql = "INSERT INTO reviews (order_id, user_id, rating, comment) VALUES (?, ?, ?, ?)";
$stmtI = $conn->prepare($insertSql);
$stmtI->bind_param("iiis", $orderId, $userId, $rating, $comment);

if ($stmtI->execute()) {
    echo json_encode(['success' => true, 'message' => 'Gửi đánh giá thành công! Cảm ơn bạn.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $stmtI->error]);
}

$conn->close();
