-- ============================================================
-- migration_fix_table_id.sql
-- Mục đích: Fix cột orders.table_id từ VARCHAR(50) sang INT
--           và thêm foreign key constraint về bảng tables(id)
--
-- ⚠️ QUAN TRỌNG: Chạy script này trên production CẦN BACKUP DB trước!
-- Lệnh backup: mysqldump -u root duong_bau_restaurant > backup_before_migration.sql
--
-- Cách chạy: mysql -u root duong_bau_restaurant < migration_fix_table_id.sql
-- ============================================================

-- Bước 0: Tắt foreign key checks tạm thời
SET FOREIGN_KEY_CHECKS = 0;

-- Bước 1: Cập nhật data bẩn – các hàng dùng tên bàn text (R2, r4) thay vì ID số
--         Truy vấn để xem data cần fix trước khi chạy:
--         SELECT id, table_id FROM orders WHERE table_id REGEXP '[^0-9]' OR table_id = '';

-- Set các hàng có table_id text/rỗng về NULL (không có FK reference hợp lệ)
UPDATE orders
SET table_id = NULL
WHERE table_id REGEXP '[^0-9]'
   OR table_id = ''
   OR table_id IS NULL;

-- Bước 2: Xác nhận không còn giá trị text nào
-- SELECT id, table_id FROM orders WHERE table_id IS NOT NULL AND table_id REGEXP '[^0-9]';
-- (Kết quả phải empty trước khi tiếp tục)

-- Bước 3: Đổi kiểu cột table_id từ VARCHAR(50) sang INT
ALTER TABLE `orders`
    MODIFY COLUMN `table_id` INT DEFAULT NULL COMMENT 'FK -> tables.id';

-- Bước 4: Thêm foreign key constraint
--         ON DELETE SET NULL: khi bàn bị xóa, order vẫn giữ nhưng table_id = NULL
ALTER TABLE `orders`
    ADD CONSTRAINT `fk_orders_table`
    FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

-- Bước 5: Thêm index nếu chưa có (phụ trợ cho JOIN query)
ALTER TABLE `orders`
    ADD INDEX `idx_orders_table_id` (`table_id`);

-- Bước 6: Bật lại foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Xác nhận kết quả
SHOW CREATE TABLE `orders`;
SELECT 'Migration completed successfully!' AS status;
