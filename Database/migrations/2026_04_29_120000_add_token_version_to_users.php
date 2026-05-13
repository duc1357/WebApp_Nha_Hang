<?php
// Database/migrations/2026_04_29_120000_add_jwt_token_to_users.php
// Migration: Thêm cột jwt_token vào bảng users để hỗ trợ token revocation
//
// Khi cần vô hiệu hóa token (logout, đổi mật khẩu), lưu "token version" vào DB.
// Client phải gửi token có đúng version mới được chấp nhận.

/**
 * Chạy migration: thêm cột token_version vào users
 */
function up(mysqli $conn): void {
    $result = $conn->query("SHOW COLUMNS FROM `users` LIKE 'token_version'");
    if ($result && $result->num_rows > 0) {
        return;
    }

    $conn->query("
        ALTER TABLE `users`
        ADD COLUMN `token_version` INT UNSIGNED NOT NULL DEFAULT 0
            COMMENT 'Tăng lên khi logout/đổi mật khẩu để vô hiệu hóa JWT cũ'
    ");

    if ($conn->errno) {
        throw new RuntimeException("Migration failed: " . $conn->error);
    }
}

/**
 * Rollback: xóa cột token_version
 */
function down(mysqli $conn): void {
    $conn->query("ALTER TABLE `users` DROP COLUMN IF EXISTS `token_version`");
}
