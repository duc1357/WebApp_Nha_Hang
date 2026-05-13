<?php
// Database/setup_scripts/setup_vouchers.php
require_once __DIR__ . '/../../api/admin/auth_check_api.php';
requireAdminPost();
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$conn = getDbConnection();

// 1. Create 'vouchers' table
$sqlVoucher = "CREATE TABLE IF NOT EXISTS vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount_type ENUM('percent', 'fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL,
    min_order_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    usage_limit INT NOT NULL DEFAULT 100,
    used_count INT NOT NULL DEFAULT 0,
    expire_date DATETIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sqlVoucher) === TRUE) {
    echo "Table 'vouchers' created successfully.\n";
} else {
    echo "Error creating table 'vouchers': " . $conn->error . "\n";
}

// 2. Alter 'orders' table to add discount columns
// Check if column exists first to avoid error
$checkCol = "SHOW COLUMNS FROM orders LIKE 'voucher_code'";
$result = $conn->query($checkCol);

if ($result->num_rows == 0) {
    $sqlAlter = "ALTER TABLE orders 
                 ADD COLUMN voucher_code VARCHAR(50) NULL AFTER total_amount,
                 ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0 AFTER voucher_code,
                 ADD COLUMN final_total DECIMAL(10,2) DEFAULT 0 AFTER discount_amount";
    
    if ($conn->query($sqlAlter) === TRUE) {
        echo "Table 'orders' altered successfully (added voucher columns).\n";
    } else {
        echo "Error altering table 'orders': " . $conn->error . "\n";
    }
} else {
    echo "Columns already exist in 'orders' table.\n";
}

$conn->close();
?>
