-- ============================================================
-- Migration: Tối ưu Database - Thêm Index & Fix Schema
-- DB-01: Fix orders.table_id type mismatch
-- DB-02: Thêm index cho orders.status, bookings.status
-- ============================================================
-- Chạy file này trong phpMyAdmin hoặc MySQL client
-- BACKUP DATABASE TRƯỚC KHI CHẠY!

-- DB-02: Thêm index cho các cột query thường xuyên
-- orders.status: WHERE status = 'pending' full table scan
ALTER TABLE `orders` ADD INDEX `idx_orders_status` (`status`);

-- orders.created_at: WHERE DATE(created_at) = ? trong get_stats.php
-- NOTE: Đây là function index, cần MySQL 8.0+ hoặc dùng generated column
-- Thay thế bằng index trên created_at (vẫn tốt hơn không có index)
ALTER TABLE `orders` ADD INDEX `idx_orders_created_at` (`created_at`);

-- bookings.status: WHERE status = 'pending' / 'confirmed'
ALTER TABLE `bookings` ADD INDEX `idx_bookings_status` (`status`);

-- bookings.date: WHERE date = ? trong check_new_bookings, get_busy_tables
ALTER TABLE `bookings` ADD INDEX `idx_bookings_date` (`date`);

-- Composite index cho query thường gặp: orders của 1 user
ALTER TABLE `orders` ADD INDEX `idx_orders_user_status` (`user_id`, `status`);

-- DB-01: Fix orders.table_id type mismatch
-- Hiện tại: orders.table_id = varchar(50), bookings.table_id = int
-- NOTE: Phần này cần kiểm tra data trước khi migrate
-- Các bước:
--   1. Kiểm tra xem có giá trị nào không phải số không
--     SELECT table_id FROM orders WHERE table_id != '' AND table_id REGEXP '[^0-9]';
--   2. Nếu chỉ có số hoặc rỗng, chạy:
--     ALTER TABLE orders MODIFY COLUMN table_id INT DEFAULT NULL;
--   3. Cập nhật code PHP (save_table_order.php đã bind_param "i" cho table_id)

-- Ví dụ kiểm tra (chạy thủ công trước):
-- SELECT DISTINCT table_id FROM orders WHERE table_id IS NOT NULL AND table_id != '';

-- Sau khi xác nhận an toàn:
-- ALTER TABLE `orders` MODIFY COLUMN `table_id` INT DEFAULT NULL;
-- ALTER TABLE `orders` ADD CONSTRAINT `fk_orders_table` FOREIGN KEY (`table_id`) REFERENCES `tables`(`id`) ON DELETE SET NULL;
