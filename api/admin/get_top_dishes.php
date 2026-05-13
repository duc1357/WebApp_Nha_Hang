<?php
// api/admin/get_top_dishes.php
if (ob_get_level()) ob_clean();
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$conn = getDbConnection();

$sql = "SELECT m.name, SUM(oi.quantity) as total_quantity
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN menu_items m ON oi.menu_item_id = m.id
        WHERE o.status = 'paid'
        GROUP BY m.id, m.name
        ORDER BY total_quantity DESC
        LIMIT 5";

$result = $conn->query($sql);
$top_dishes = [];
$labels = [];
$data = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $top_dishes[] = [
            'name' => $row['name'],
            'quantity' => (int)$row['total_quantity']
        ];
        $labels[] = $row['name'];
        $data[] = (int)$row['total_quantity'];
    }
}

echo json_encode([
    'success' => true, 
    'labels' => $labels,
    'data' => $data,
    'details' => $top_dishes
], JSON_UNESCAPED_UNICODE);

$conn->close();
