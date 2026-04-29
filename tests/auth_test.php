<?php
// tests/auth_test.php
// Integration Tests cho Auth Flow (Login, CSRF, Rate Limit)
// Chạy: php tests/run_tests.php auth

if (!defined('TEST_RUNNER')) {
    define('TEST_RUNNER', true);
    require_once __DIR__ . '/../config/constants.php';
    require_once __DIR__ . '/run_tests.php';
}

require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';
require_once ROOT_PATH . '/api/services/rate_limit_service.php';
require_once ROOT_PATH . '/api/services/jwt_service.php';
require_once ROOT_PATH . '/api/base.php';

// Helper: Define constant only if not already defined
function define_if_not_set(string $name, mixed $value): void {
    if (!defined($name)) define($name, $value);
}

$conn = getDbConnection();

TestSuite::start('Auth & Security Integration Tests');

// =============================================
// TEST GROUP 1: CSRF Token
// =============================================

// --- Test 1.1: Token generation ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];

$token1 = CsrfService::generateToken();
TestSuite::assert(!empty($token1), 'CSRF: generateToken() trả về chuỗi không rỗng');
TestSuite::assert(strlen($token1) === 64, 'CSRF: Token có độ dài 64 ký tự');

// --- Test 1.2: Token persistence ---
$token2 = CsrfService::generateToken();
TestSuite::assertEquals($token1, $token2, 'CSRF: generateToken() trả về cùng token trong cùng session');

// --- Test 1.3: Token rotation ---
$tokenBefore = CsrfService::generateToken();
CsrfService::rotateToken();
$tokenAfter = CsrfService::generateToken();
TestSuite::assert($tokenBefore !== $tokenAfter, 'CSRF: rotateToken() tạo token mới khác token cũ');

// --- Test 1.4: Valid token validation ---
$validToken = CsrfService::generateToken();
TestSuite::assert(
    CsrfService::verifyToken($validToken),
    'CSRF: verifyToken() trả về true với token hợp lệ'
);

// --- Test 1.5: Invalid token ---
TestSuite::assert(
    !CsrfService::verifyToken('invalid_token_xyz'),
    'CSRF: verifyToken() trả về false với token sai'
);

// =============================================
// TEST GROUP 2: Rate Limiter
// =============================================

// Reset rate limit state cho IP test
$testKey = 'test_rate_' . uniqid();

// --- Test 2.1: Cho phép requests trong giới hạn ---
$passed = true;
for ($i = 0; $i < 3; $i++) {
    if (!RateLimitService::check($testKey, 5, 60)) {
        $passed = false;
        break;
    }
}
TestSuite::assert($passed, 'RateLimit: Cho phép 3 requests khi limit là 5/phút');

// --- Test 2.2: Block khi vượt limit ---
for ($i = 0; $i < 3; $i++) {
    RateLimitService::check($testKey, 5, 60); // Dùng thêm 2 lần (tổng 5)
}
$blocked = !RateLimitService::check($testKey, 5, 60); // Lần thứ 6 phải bị block
TestSuite::assert($blocked, 'RateLimit: Block request thứ 6 khi limit là 5/phút');

// --- Test 2.3: Key khác không bị ảnh hưởng ---
$newKey = 'test_rate_different_' . uniqid();
TestSuite::assert(
    RateLimitService::check($newKey, 5, 60),
    'RateLimit: Key khác không bị ảnh hưởng bởi rate limit của key khác'
);

// =============================================
// TEST GROUP 3: Database Connectivity & User Queries
// =============================================

// --- Test 3.1: DB connection ---
TestSuite::assert($conn instanceof mysqli, 'DB: Kết nối database thành công');
TestSuite::assert($conn->ping(), 'DB: Connection ping thành công');

// --- Test 3.2: Users table tồn tại ---
$tables = $conn->query("SHOW TABLES LIKE 'users'")->fetch_all();
TestSuite::assert(!empty($tables), "DB: Bảng 'users' tồn tại");

// --- Test 3.3: Orders table tồn tại ---
$tables = $conn->query("SHOW TABLES LIKE 'orders'")->fetch_all();
TestSuite::assert(!empty($tables), "DB: Bảng 'orders' tồn tại");

// --- Test 3.4: orders.table_id kiểu INT (sau migration) ---
$col = $conn->query("
    SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = '" . DB_NAME . "'
      AND TABLE_NAME   = 'orders'
      AND COLUMN_NAME  = 'table_id'
")->fetch_assoc();
TestSuite::assert(
    $col && str_starts_with(strtolower($col['COLUMN_TYPE']), 'int'),
    "DB: orders.table_id đã là kiểu INT (migration đã chạy)"
);

// --- Test 3.5: FK constraint tồn tại ---
$fk = $conn->query("
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA      = '" . DB_NAME . "'
      AND TABLE_NAME        = 'orders'
      AND COLUMN_NAME       = 'table_id'
      AND REFERENCED_TABLE_NAME IS NOT NULL
")->fetch_assoc();
TestSuite::assert($fk !== null, "DB: FK constraint 'orders.table_id → tables.id' tồn tại");

// =============================================
// TEST GROUP 4: Input Sanitization (api/base.php)
// =============================================

// --- Test 4.1: getParam sanitize string ---
$data    = ['name' => '<script>alert(1)</script>', 'age' => '25', 'email' => 'test@test.com'];
$name    = getParam($data, 'name',  null, 'string');
$age     = getParam($data, 'age',   null, 'int');
$email   = getParam($data, 'email', null, 'email');
$missing = getParam($data, 'missing', 'default_value');

TestSuite::assert(!str_contains($name, '<script>'), 'Sanitize: XSS bị loại bỏ khỏi string param');
TestSuite::assertEquals(25, $age, 'Sanitize: getParam int casting đúng');
TestSuite::assertEquals('test@test.com', $email, 'Sanitize: Email hợp lệ được chấp nhận');
TestSuite::assertEquals('default_value', $missing, 'Sanitize: Giá trị mặc định khi key không tồn tại');

// --- Test 4.2: Email validation ---
$badEmail = getParam(['email' => 'not-an-email'], 'email', null, 'email');
TestSuite::assert($badEmail === null, 'Sanitize: Email không hợp lệ trả về null');

$conn->close();
