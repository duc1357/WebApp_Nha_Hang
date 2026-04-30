<?php
// api/user/submit_review.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';

// SEC-02: Validate CSRF
CsrfService::validateRequest();

// Check Login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để đánh giá']);
    exit;
}
$userId = (int) $_SESSION['user_id'];

$data = json_decode(file_get_contents("php://input"), true);
$orderId = isset($data['order_id']) ? (int) $data['order_id'] : 0;
$rating  = isset($data['rating'])   ? (int) $data['rating']   : 0;
$comment = isset($data['comment'])  ? trim($data['comment'])  : '';

if (!$orderId || !$rating) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin bắt buộc']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Đánh giá phải từ 1 đến 5 sao']);
    exit;
}

// CODE-11: Chỉ tạo 1 DB connection (bỏ connection trùng lặp ở đầu file cũ)
$conn = getDbConnection();

// 1. Check ownership and status
$checkSql = "SELECT id, status FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($checkSql);
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Đơn hàng không tồn tại hoặc không thuộc về bạn']);
    $stmt->close();
    $conn->close();
    exit;
}

$order = $result->fetch_assoc();
$stmt->close();

if ($order['status'] !== 'paid') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Bạn chỉ có thể đánh giá đơn hàng đã hoàn thành (đã thanh toán)']);
    $conn->close();
    exit;
}

// 2. Check if already reviewed
$checkReview = "SELECT id FROM reviews WHERE order_id = ?";
$stmtR = $conn->prepare($checkReview);
$stmtR->bind_param("i", $orderId);
$stmtR->execute();
if ($stmtR->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Bạn đã đánh giá đơn hàng này rồi']);
    $stmtR->close();
    $conn->close();
    exit;
}
$stmtR->close();

// 3. Insert Review
$insertSql = "INSERT INTO reviews (order_id, user_id, rating, comment) VALUES (?, ?, ?, ?)";
$stmtI = $conn->prepare($insertSql);
$stmtI->bind_param("iiis", $orderId, $userId, $rating, $comment);

if ($stmtI->execute()) {
    echo json_encode(['success' => true, 'message' => 'Gửi đánh giá thành công! Cảm ơn bạn.']);
} else {
    // CODE-06: Không lộ $stmt->error
    error_log('[Review] Insert failed: ' . $stmtI->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại.']);
}

$stmtI->close();
$conn->close();
