<?php
// Database/setup_scripts/setup_performance.php
// [2.3] Chỉ admin mới được chạy migration/setup scripts
require_once __DIR__ . '/../../api/admin/auth_check_api.php';
requireAdminPost();
header('Content-Type: text/plain; charset=utf-8');
require_once ROOT_PATH . '/config/db.php';

$conn = getDbConnection();

function addIndexIfNotExists($conn, $table, $indexName, $column) {
    // Check if index exists
    $check = $conn->query("SHOW INDEX FROM $table WHERE Key_name = '$indexName'");
    if ($check->num_rows > 0) {
        echo "Index '$indexName' already exists on table '$table'.\n";
    } else {
        $sql = "ALTER TABLE $table ADD INDEX $indexName ($column)";
        if ($conn->query($sql)) {
            echo "✅ Added index '$indexName' to '$table' ($column).\n";
        } else {
            echo "❌ Error adding index '$indexName': " . $conn->error . "\n";
        }
    }
}

echo "--- STARTING DATABASE OPTIMIZATION ---\n";

// 1. ORDERS Table
addIndexIfNotExists($conn, 'orders', 'idx_orders_user_id', 'user_id');
addIndexIfNotExists($conn, 'orders', 'idx_orders_status', 'status');
addIndexIfNotExists($conn, 'orders', 'idx_orders_created_at', 'created_at');

// 2. BOOKINGS Table
addIndexIfNotExists($conn, 'bookings', 'idx_bookings_user_id', 'user_id');
addIndexIfNotExists($conn, 'bookings', 'idx_bookings_date', 'date');
addIndexIfNotExists($conn, 'bookings', 'idx_bookings_status', 'status');

// 3. USERS Table (Email & Phone usually unique, but index helps search)
addIndexIfNotExists($conn, 'users', 'idx_users_role', 'role');
addIndexIfNotExists($conn, 'users', 'idx_users_created_at', 'created_at');

// 4. VOUCHERS
addIndexIfNotExists($conn, 'vouchers', 'idx_vouchers_code', 'code');
addIndexIfNotExists($conn, 'vouchers', 'idx_vouchers_active', 'is_active');

echo "--- OPTIMIZATION COMPLETED ---\n";

$conn->close();
?>
