<?php
// tests/run_tests.php
// Test Runner – Chạy toàn bộ integration tests
//
// Cách dùng (từ thư mục root):
//   php tests/run_tests.php                  → Chạy tất cả tests
//   php tests/run_tests.php auth             → Chỉ chạy auth_test.php
//   php tests/run_tests.php payment          → Chỉ chạy payment_test.php
//   php tests/run_tests.php jwt              → Chỉ chạy jwt_test.php
//
// Kết quả: In ra PASS/FAIL với thời gian thực thi

define('TEST_RUNNER', true);

// Config test environment
require_once __DIR__ . '/../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

// =============================================
// TEST FRAMEWORK (Minimal, no dependencies)
// =============================================
class TestSuite
{
    private static int   $passed  = 0;
    private static int   $failed  = 0;
    private static float $startAt = 0;
    private static array $failures = [];

    public static function start(string $suiteName): void
    {
        self::$startAt = microtime(true);
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🧪 Running: {$suiteName}\n";
        echo str_repeat('=', 60) . "\n";
    }

    /**
     * Assert một điều kiện là đúng
     * @param bool   $condition  Điều kiện cần kiểm tra
     * @param string $testName   Mô tả test case
     * @param string $errorMsg   Thông báo khi thất bại
     */
    public static function assert(bool $condition, string $testName, string $errorMsg = ''): void
    {
        if ($condition) {
            echo "  ✅ PASS: {$testName}\n";
            self::$passed++;
        } else {
            echo "  ❌ FAIL: {$testName}";
            if ($errorMsg) echo " → {$errorMsg}";
            echo "\n";
            self::$failed++;
            self::$failures[] = ['test' => $testName, 'error' => $errorMsg];
        }
    }

    /** Assert hai giá trị bằng nhau */
    public static function assertEquals(mixed $expected, mixed $actual, string $testName): void
    {
        self::assert(
            $expected === $actual,
            $testName,
            "Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true)
        );
    }

    /** Assert giá trị không null */
    public static function assertNotNull(mixed $value, string $testName): void
    {
        self::assert($value !== null, $testName, "Expected non-null, got null");
    }

    /** Assert array có key */
    public static function assertArrayHasKey(string $key, array $array, string $testName): void
    {
        self::assert(array_key_exists($key, $array), $testName, "Key '{$key}' not found in array");
    }

    public static function summary(): void
    {
        $elapsed  = round(microtime(true) - self::$startAt, 3);
        $total    = self::$passed + self::$failed;
        $passed   = self::$passed;
        $failed   = self::$failed;
        $failures = self::$failures;

        echo "\n" . str_repeat('-', 60) . "\n";
        echo "📊 Results: {$total} tests | ✅ {$passed} passed | ❌ {$failed} failed";
        echo " | ⏱ {$elapsed}s\n";

        if (!empty($failures)) {
            echo "\n❗ Failed Tests:\n";
            foreach ($failures as $f) {
                echo "   • {$f['test']}: {$f['error']}\n";
            }
        }
        echo str_repeat('=', 60) . "\n\n";
    }

    public static function hasFailed(): bool { return self::$failed > 0; }
    public static function reset(): void { self::$passed = 0; self::$failed = 0; self::$failures = []; }
}

// =============================================
// DISCOVER & RUN TEST FILES
// =============================================
$filter    = $argv[1] ?? null;
$testFiles = glob(__DIR__ . '/*_test.php');
$exitCode  = 0;

$totalPassed = 0;
$totalFailed = 0;

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║         🍜 Cơm Quê Dượng Bầu – Test Suite                ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";

foreach ($testFiles as $file) {
    $baseName = basename($file, '_test.php');

    // Filter nếu có argument
    if ($filter && stripos($baseName, $filter) === false) continue;

    TestSuite::reset();
    require $file;
    TestSuite::summary();

    if (TestSuite::hasFailed()) $exitCode = 1;
}

if (empty($testFiles)) {
    echo "⚠️  Không tìm thấy file test nào.\n\n";
}

exit($exitCode);
