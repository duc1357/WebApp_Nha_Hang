<?php
// api/admin/get_revenue_stats.php
ob_clean();
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$conn = getDbConnection();

$type = isset($_GET['type']) ? $_GET['type'] : 'day';
$response = [];

if ($type === 'month') {
    // Get monthly revenue for the current year
    $year = date('Y');
    $stats = array_fill(1, 12, 0);
    $labels = [];
    for ($i = 1; $i <= 12; $i++) {
        $labels[] = "Tháng $i";
    }

    $sql = "SELECT MONTH(created_at) as month, SUM(CASE WHEN final_total > 0 THEN final_total ELSE total_amount END) as total 
            FROM orders 
            WHERE status = 'paid' 
            AND YEAR(created_at) = ?
            GROUP BY MONTH(created_at)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $year);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats[(int)$row['month']] = (float)$row['total'];
        }
    }
    
    $response = [
        'labels' => $labels,
        'data' => array_values($stats),
        'total_year' => array_sum($stats)
    ];
    $stmt->close();
} else {
    // Get last 7 days (including today)
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
}

echo json_encode(['success' => true, 'stats' => $response]);
$conn->close();
