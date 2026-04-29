<?php
// Database/migrate.php
// Migration Runner – Version control cho Database Schema
//
// Cách dùng (từ thư mục root):
//   php Database/migrate.php           → Chạy tất cả migrations chưa được chạy
//   php Database/migrate.php status    → Xem trạng thái các migrations
//   php Database/migrate.php rollback  → Rollback migration cuối cùng (nếu có down())
//
// Quy tắc tên file migration: YYYY_MM_DD_HHMMSS_ten_migration.php
//   Ví dụ: 2026_04_29_100000_create_migrations_table.php
//
// Mỗi file migration phải có 2 hàm:
//   up($conn)   → Thực hiện migration
//   down($conn) → Rollback (optional nhưng nên có)

// =============================================
// BOOTSTRAP
// =============================================
define('MIGRATION_RUNNER', true);

require_once __DIR__ . '/../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$conn = getDbConnection();
if (!$conn) {
    echo "[FATAL] Không thể kết nối database.\n";
    exit(1);
}

// =============================================
// TẠO BẢNG MIGRATION TRACKER (nếu chưa có)
// =============================================
$conn->query("
    CREATE TABLE IF NOT EXISTS `_migrations` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `migration`  VARCHAR(255) NOT NULL UNIQUE,
        `batch`      INT UNSIGNED NOT NULL DEFAULT 1,
        `run_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// =============================================
// ĐỌC LỆNH
// =============================================
$command = $argv[1] ?? 'migrate';

$migrationsDir = __DIR__ . '/migrations';
$migrationFiles = glob($migrationsDir . '/*.php');
sort($migrationFiles); // Sắp xếp theo tên (datetime prefix đảm bảo thứ tự)

// =============================================
// HÀM TIỆN ÍCH
// =============================================
function getRanMigrations(mysqli $conn): array {
    $result = $conn->query("SELECT migration FROM `_migrations` ORDER BY id ASC");
    if (!$result) return [];
    return array_column($result->fetch_all(MYSQLI_ASSOC), 'migration');
}

function getCurrentBatch(mysqli $conn): int {
    $result = $conn->query("SELECT MAX(batch) as max_batch FROM `_migrations`");
    $row    = $result?->fetch_assoc();
    return (int)($row['max_batch'] ?? 0);
}

function runMigration(mysqli $conn, string $file, int $batch): void {
    $name = basename($file, '.php');
    echo "  → Migrating: {$name} ... ";

    require_once $file;

    try {
        $conn->begin_transaction();
        up($conn);
        $stmt = $conn->prepare("INSERT INTO `_migrations` (migration, batch) VALUES (?, ?)");
        $stmt->bind_param('si', $name, $batch);
        $stmt->execute();
        $conn->commit();
        echo "✅ Done\n";
    } catch (Throwable $e) {
        $conn->rollback();
        echo "❌ FAILED: " . $e->getMessage() . "\n";
    }
}

// =============================================
// COMMANDS
// =============================================
switch ($command) {

    case 'migrate':
        $ran   = getRanMigrations($conn);
        $batch = getCurrentBatch($conn) + 1;
        $pending = array_filter($migrationFiles, fn($f) => !in_array(basename($f, '.php'), $ran));

        if (empty($pending)) {
            echo "✅ Không có migration nào cần chạy.\n";
            break;
        }

        echo "🚀 Running " . count($pending) . " migration(s) – Batch {$batch}:\n";
        foreach ($pending as $file) {
            runMigration($conn, $file, $batch);
        }
        echo "\n✅ Migration hoàn tất!\n";
        break;

    case 'status':
        $ran = getRanMigrations($conn);
        echo "📋 Migration Status:\n";
        echo str_repeat('-', 60) . "\n";
        foreach ($migrationFiles as $file) {
            $name   = basename($file, '.php');
            $status = in_array($name, $ran) ? '✅ Ran' : '⬜ Pending';
            echo sprintf("  %-50s %s\n", $name, $status);
        }
        echo str_repeat('-', 60) . "\n";
        break;

    case 'rollback':
        $batch  = getCurrentBatch($conn);
        if ($batch === 0) { echo "❌ Không có migration nào để rollback.\n"; break; }

        $result = $conn->prepare("SELECT migration FROM `_migrations` WHERE batch = ? ORDER BY id DESC");
        $result->bind_param('i', $batch);
        $result->execute();
        $toRollback = array_column($result->get_result()->fetch_all(MYSQLI_ASSOC), 'migration');

        echo "⏪ Rolling back Batch {$batch} (" . count($toRollback) . " migrations):\n";
        foreach ($toRollback as $name) {
            $file = $migrationsDir . '/' . $name . '.php';
            if (!file_exists($file)) { echo "  ⚠️  File không tồn tại: {$name}\n"; continue; }

            require_once $file;
            echo "  ← Rolling back: {$name} ... ";
            try {
                $conn->begin_transaction();
                if (function_exists('down')) down($conn);
                $delStmt = $conn->prepare("DELETE FROM `_migrations` WHERE migration = ?");
                $delStmt->bind_param('s', $name);
                $delStmt->execute();
                $conn->commit();
                echo "✅ Done\n";
            } catch (Throwable $e) {
                $conn->rollback();
                echo "❌ FAILED: " . $e->getMessage() . "\n";
            }
        }
        echo "\n✅ Rollback hoàn tất!\n";
        break;

    default:
        echo "❓ Lệnh không hợp lệ. Dùng: migrate | status | rollback\n";
}

$conn->close();
