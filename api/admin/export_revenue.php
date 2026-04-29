<?php
// api/admin/export_revenue.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../admin/auth_check.php'; // Ensure admin only

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=doanh_thu_' . date('Y-m-d') . '.csv');

// Create file pointer connected to output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Headers
fputcsv($output, ['Mã đơn hàng', 'Khách hàng', 'Số điện thoại', 'Tổng tiền (Gốc)', 'Giảm giá', 'Thực thu', 'Voucher', 'Trạng thái', 'Ngày đặt']);

$conn = getDbConnection();

// Get all orders
$sql = "SELECT o.id, u.name as customer_name, u.phone as customer_phone, o.total_amount, o.discount_amount, o.final_total, o.voucher_code, o.status, o.created_at 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC";
$result = $conn->query($sql);

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

        fputcsv($output, [
            $row['id'],
            $row['customer_name'] ?? 'Khách lẻ',
            $row['customer_phone'] ?? '-',
            number_format($row['total_amount'], 0, ',', '.'),
            number_format($discount, 0, ',', '.'),
            number_format($final, 0, ',', '.'),
            $row['voucher_code'] ?? '',
            $status,
            $row['created_at']
        ]);
    }
}

fclose($output);
$conn->close();
exit;
