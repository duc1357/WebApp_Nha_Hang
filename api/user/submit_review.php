<?php
// api/user/submit_review.php
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';
require_once ROOT_PATH . '/api/services/rate_limit_service.php';
require_once ROOT_PATH . '/api/services/auth_state_service.php';

// SEC-02: Validate CSRF
CsrfService::validateRequest();

// Check Login
$authUser = AuthStateService::requireSession();
$userId = (int)$authUser['id'];

if (!RateLimitService::check('submit_review_' . $userId, 3, 60)) {
    ResponseService::error('Thao tác quá nhanh. Vui lòng đợi 1 phút.', 429);
}

$data = json_decode(file_get_contents("php://input"), true);
$orderId = isset($data['order_id']) ? (int) $data['order_id'] : 0;
$rating  = isset($data['rating'])   ? (int) $data['rating']   : 0;
$comment = isset($data['comment'])  ? trim($data['comment'])  : '';

if (!$orderId || !$rating) {
    ResponseService::error('Thiếu thông tin bắt buộc', 400);
}

if ($rating < 1 || $rating > 5) {
    ResponseService::error('Đánh giá phải từ 1 đến 5 sao', 400);
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
    $stmt->close();
    $conn->close();
    ResponseService::error('Đơn hàng không tồn tại hoặc không thuộc về bạn', 403);
}

$order = $result->fetch_assoc();
$stmt->close();

if ($order['status'] !== 'paid') {
    $conn->close();
    ResponseService::error('Bạn chỉ có thể đánh giá đơn hàng đã hoàn thành (đã thanh toán)', 400);
}

// 2. Check if already reviewed
$checkReview = "SELECT id FROM reviews WHERE order_id = ?";
$stmtR = $conn->prepare($checkReview);
$stmtR->bind_param("i", $orderId);
$stmtR->execute();
if ($stmtR->get_result()->num_rows > 0) {
    $stmtR->close();
    $conn->close();
    ResponseService::error('Bạn đã đánh giá đơn hàng này rồi', 409);
}
$stmtR->close();

// 3. Insert Review
$insertSql = "INSERT INTO reviews (order_id, user_id, rating, comment) VALUES (?, ?, ?, ?)";
$stmtI = $conn->prepare($insertSql);
$stmtI->bind_param("iiis", $orderId, $userId, $rating, $comment);

if ($stmtI->execute()) {
    $stmtI->close();
    $conn->close();
    ResponseService::success(['message' => 'Gửi đánh giá thành công! Cảm ơn bạn.']);
} else {
    // CODE-06: Không lộ $stmt->error
    error_log('[Review] Insert failed: ' . $stmtI->error);
    $stmtI->close();
    $conn->close();
    ResponseService::error('Lỗi hệ thống. Vui lòng thử lại.', 500);
}
