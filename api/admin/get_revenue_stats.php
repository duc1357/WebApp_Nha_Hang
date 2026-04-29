<?php
// api/admin/get_revenue_stats.php
ob_clean();
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$conn = getDbConnection();

// Get last 7 days
// Including today
$dates = [];
for ($i = 6; $i >= 0; $i--) {
    $dates[] = date('Y-m-d', strtotime("-$i days"));
}

// Prepare result structure
$stats = array_fill_keys($dates, 0);

$sql = "SELECT DATE(created_at) as date, SUM(CASE WHEN final_total > 0 THEN final_total ELSE total_amount END) as total 
        FROM orders 
        WHERE status = 'paid' 
        AND created_at >= DATE(NOW() - INTERVAL 7 DAY)
        GROUP BY DATE(created_at)";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stats[$row['date']] = (float)$row['total'];
    }
}

$response = [
    'labels' => array_keys($stats),
    'data' => array_values($stats),
    'total_week' => array_sum($stats)
];

echo json_encode(['success' => true, 'stats' => $response]);
$conn->close();
