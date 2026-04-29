<?php
// tests/jwt_test.php
// Unit Tests cho JwtService
// Chạy: php tests/run_tests.php jwt

if (!defined('TEST_RUNNER')) {
    define('TEST_RUNNER', true);
    require_once __DIR__ . '/../config/constants.php';
    require_once __DIR__ . '/run_tests.php';
}

require_once ROOT_PATH . '/api/services/jwt_service.php';

// Helper: Define constant only if not already defined
if (!function_exists('define_if_not_set')) {
    function define_if_not_set(string $name, mixed $value): void {
        if (!defined($name)) define($name, $value);
    }
}

// Fallback JWT_SECRET nếu chưa có trong .env (chỉ cho test)
define_if_not_set('JWT_SECRET', 'test_secret_key_for_unit_testing_only_32chars!!');
define_if_not_set('JWT_TTL_SECONDS', 3600);

// =============================================
// TEST SUITE: JWT Service
// =============================================
TestSuite::start('JwtService Unit Tests');

// --- Test 1: Generate token ---
$payload = ['user_id' => 1, 'role' => 'user', 'name' => 'Test User'];
$token   = null;

try {
    $token = JwtService::generate($payload);
    TestSuite::assert(!empty($token), 'generate() trả về token không rỗng');
    TestSuite::assert(substr_count($token, '.') === 2, 'Token có đúng 3 phần (header.payload.signature)');
} catch (Throwable $e) {
    TestSuite::assert(false, 'generate() không ném exception', $e->getMessage());
}

// --- Test 2: Verify token hợp lệ ---
if ($token) {
    $decoded = JwtService::verify($token);
    TestSuite::assert($decoded !== false, 'verify() thành công với token hợp lệ');
    TestSuite::assertEquals(1,      $decoded['user_id'] ?? null, 'verify() giải mã đúng user_id');
    TestSuite::assertEquals('user', $decoded['role']    ?? null, 'verify() giải mã đúng role');
    TestSuite::assertArrayHasKey('iat', $decoded, 'Payload có claim iat');
    TestSuite::assertArrayHasKey('exp', $decoded, 'Payload có claim exp');
}

// --- Test 3: Token bị sửa chữa (tampered) ---
if ($token) {
    $parts       = explode('.', $token);
    $parts[1]   .= 'tampered'; // Sửa payload
    $badToken    = implode('.', $parts);
    $result      = JwtService::verify($badToken);
    TestSuite::assert($result === false, 'verify() từ chối token bị sửa payload');
}

// --- Test 4: Token sai signature ---
$fakeToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo5OSwidWlkIjoiZmFrZSJ9.fake_signature_here';
TestSuite::assert(JwtService::verify($fakeToken) === false, 'verify() từ chối token signature sai');

// --- Test 5: Token hết hạn ---
$expiredToken = JwtService::generate(['user_id' => 2], -1); // TTL = -1 giây (expired ngay lập tức)
TestSuite::assert(JwtService::verify($expiredToken) === false, 'verify() từ chối token đã hết hạn');

// --- Test 6: Refresh token ---
if ($token) {
    $newToken = JwtService::refresh($token);
    TestSuite::assert($newToken !== false, 'refresh() tạo được token mới từ token còn hạn');
    // Token mới phải hợp lệ (dù có thể giống token cũ nếu gọi trong cùng giây)
    $newDecoded = $newToken ? JwtService::verify($newToken) : false;
    TestSuite::assert($newDecoded !== false, 'Token sau refresh() vẫn hợp lệ và verify được');

    $newDecoded2 = JwtService::verify($newToken);
    TestSuite::assertEquals(1, $newDecoded2['user_id'] ?? null, 'Token mới giữ nguyên user_id');
}

// --- Test 7: Refresh token đã hết hạn quá 30 phút (không được phép) ---
$veryOldToken = JwtService::generate(['user_id' => 3], -(31 * 60)); // Hết hạn 31 phút trước
TestSuite::assert(JwtService::refresh($veryOldToken) === false, 'refresh() từ chối token quá grace period (> 30 phút)');

// --- Test 8: Issuer claim ---
if ($token) {
    $decoded = JwtService::verify($token);
    TestSuite::assertEquals('duong-bau-restaurant', $decoded['iss'] ?? null, 'Token có issuer đúng');
}

// (helper được khai báo ở đầu file)
