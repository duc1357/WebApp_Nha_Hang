<?php
header('Content-Type: text/html; charset=utf-8');
// [2.3] Chỉ admin mới được chạy script ALTER TABLE
require_once __DIR__ . '/../../api/admin/auth_check_api.php';
requireAdminPost();
require_once ROOT_PATH . '/config/db.php';

$conn = getDbConnection();

echo "<h1>Updating Database for OTP...</h1>";

// 1. Check if columns exist
$res = $conn->query("SHOW COLUMNS FROM users LIKE 'otp_code'");
if ($res->num_rows == 0) {
    // Add columns
    $sql = "ALTER TABLE users 
            ADD COLUMN otp_code VARCHAR(6) NULL AFTER role,
            ADD COLUMN otp_expiry DATETIME NULL AFTER otp_code";
    
    if ($conn->query($sql)) {
        echo "<p style='color:green'>✅ Added `otp_code` and `otp_expiry` columns.</p>";
    } else {
        echo "<p style='color:red'>❌ Error adding columns: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange'>⚠️ Columns already exist.</p>";
}

// 2. Clear old OTPs
$conn->query("UPDATE users SET otp_code = NULL, otp_expiry = NULL");
echo "<p>✅ Cleaned up old OTP data.</p>";

$conn->close();
echo "<br><a href='../../index.html'>Go Home</a>";
