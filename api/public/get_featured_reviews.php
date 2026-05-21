<?php
// api/public/get_featured_reviews.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/response_service.php';

$conn = getDbConnection();

$sql = "SELECT r.rating, r.comment, r.created_at, u.name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.rating = 5
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT 3";

$result = $conn->query($sql);
$reviews = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = [
            'name' => $row['name'],
            'rating' => (int)$row['rating'],
            'comment' => $row['comment'],
            'created_at' => $row['created_at'],
        ];
    }
}

if (empty($reviews)) {
    $reviews = [
        ['name' => 'Nguyễn Văn A', 'rating' => 5, 'comment' => 'Món ăn rất ngon, đậm đà hương vị quê hương. Chắc chắn sẽ quay lại!'],
        ['name' => 'Trần Thị B', 'rating' => 5, 'comment' => 'Phục vụ chu đáo, không gian ấm cúng. Giá cả hợp lý.'],
        ['name' => 'Lê Văn C', 'rating' => 5, 'comment' => 'Thích nhất món cá kho tộ ở đây. Tuyệt vời!'],
    ];
}

$conn->close();
ResponseService::success(['reviews' => $reviews]);
