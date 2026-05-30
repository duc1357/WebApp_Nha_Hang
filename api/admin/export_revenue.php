<?php
// api/admin/export_revenue.php
// [2.3] Standardized auth middleware
require_once __DIR__ . '/auth_check_api.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/csv_service.php';
require_once ROOT_PATH . '/api/services/logger_service.php';

$timezone = new DateTimeZone('Asia/Ho_Chi_Minh');
$today = new DateTimeImmutable('today', $timezone);
$maxExportDays = 93;

$fromInput = trim((string)($_GET['from'] ?? $today->modify('-30 days')->format('Y-m-d')));
$toInput = trim((string)($_GET['to'] ?? $today->format('Y-m-d')));

$fromDate = DateTimeImmutable::createFromFormat('!Y-m-d', $fromInput, $timezone);
$toDate = DateTimeImmutable::createFromFormat('!Y-m-d', $toInput, $timezone);

if (!$fromDate || !$toDate || $fromDate > $toDate) {
    http_response_code(400);
    echo 'Invalid export date range';
    exit;
}

$daySpan = $fromDate->diff($toDate)->days + 1;
if ($daySpan > $maxExportDays) {
    http_response_code(422);
    echo 'Export range cannot exceed ' . $maxExportDays . ' days';
    exit;
}

Logger::app('Admin revenue export requested', [
    'admin_id' => (int)($_SESSION['user_id'] ?? 0),
    'from' => $fromDate->format('Y-m-d'),
    'to' => $toDate->format('Y-m-d'),
]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=doanh_thu_' . date('Y-m-d') . '.csv');

// Create file pointer connected to output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Headers
fputcsv($output, ['Mã đơn hàng', 'Khách hàng', 'Số điện thoại', 'Tổng tiền (Gốc)', 'Giảm giá', 'Thực thu', 'Voucher', 'Trạng thái', 'Ngày đặt']);

$conn = getDbConnection();

$sql = "SELECT o.id, u.name as customer_name, u.phone as customer_phone, o.total_amount, o.discount_amount, o.final_total, o.voucher_code, o.status, o.created_at
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        ORDER BY o.created_at DESC";
$stmt = $conn->prepare($sql);
$fromValue = $fromDate->format('Y-m-d');
$toValue = $toDate->format('Y-m-d');
$stmt->bind_param('ss', $fromValue, $toValue);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Translate status
        $statusMap = [
            'pending' => 'Chờ xử lý',
            'paid' => 'Đã thanh toán',
            'cancelled' => 'Đã hủy'
        ];
        $status = $statusMap[$row['status']] ?? $row['status'];

        // Logic check: if final_total is 0 or null, use total_amount (old orders)
        $final = ($row['final_total'] > 0) ? $row['final_total'] : $row['total_amount'];
        $discount = $row['discount_amount'] ?? 0;

        fputcsv($output, CsvService::safeRow([
            $row['id'],
            $row['customer_name'] ?? 'Khách lẻ',
            $row['customer_phone'] ?? '-',
            number_format($row['total_amount'], 0, ',', '.'),
            number_format($discount, 0, ',', '.'),
            number_format($final, 0, ',', '.'),
            $row['voucher_code'] ?? '',
            $status,
            $row['created_at']
        ]));
    }
}

$stmt->close();
fclose($output);
$conn->close();
exit;
