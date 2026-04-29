<?php
// api/public/get_featured_reviews.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$conn = getDbConnection();

// SELECT 5-star reviews only, join with users to get name (and maybe avatar later)
$sql = "SELECT r.rating, r.comment, r.created_at, u.name 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.rating = 5 
        ORDER BY RAND() 
        LIMIT 3";

$result = $conn->query($sql);
$reviews = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
}

// Fallback fake reviews if database has no reviews yet (for demo purpose)
if (empty($reviews)) {
    $reviews = [
        ['name' => 'Nguyễn Văn A', 'rating' => 5, 'comment' => 'Món ăn rất ngon, đậm đà hương vị quê hương. Chắc chắn sẽ quay lại!'],
        ['name' => 'Trần Thị B', 'rating' => 5, 'comment' => 'Phục vụ chu đáo, không gian ấm cúng. Giá cả hợp lý.'],
        ['name' => 'Lê Văn C', 'rating' => 5, 'comment' => 'Thích nhất món cá kho tộ ở đây. Tuyệt vời!']
    ];
}

echo json_encode(['success' => true, 'reviews' => $reviews]);
$conn->close();
