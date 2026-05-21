<?php
// tests/test_redis.php
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../api/services/rate_limit_service.php';

echo "=== KIỂM THỬ HỆ THỐNG RATE LIMIT DUAL-DRIVER (LARAGON APACHE ENVIRONMENT) ===\n\n";

// Giả lập IP nếu chạy CLI hoặc không có remote addr
if (empty($_SERVER['REMOTE_ADDR'])) {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

// 1. Kiểm tra Extension & Server Redis
$hasRedisExt = class_exists('Redis');
echo "1. Trạng thái extension PHP Redis: " . ($hasRedisExt ? "ĐÃ NẠP (AVAILABLE)" : "CHƯA NẠP (MISSING)") . "\n";
echo "   Cấu hình REDIS_ENABLED  : " . (REDIS_ENABLED ? "TRUE" : "FALSE") . "\n";
echo "   Cấu hình REDIS_HOST     : " . REDIS_HOST . ":" . REDIS_PORT . "\n";

$redisConnected = false;
if ($hasRedisExt && REDIS_ENABLED) {
    try {
        $redis = new Redis();
        $redisConnected = @$redis->connect(REDIS_HOST, REDIS_PORT, 1.0);
        if ($redisConnected) {
            if (!empty(REDIS_PASS)) {
                $redis->auth(REDIS_PASS);
            }
            echo "   Kết nối Redis Server    : THÀNH CÔNG (CONNECTED)\n";
        } else {
            echo "   Kết nối Redis Server    : THẤT BẠI (DISCONNECTED)\n";
        }
    } catch (Throwable $e) {
        echo "   Kết nối Redis Server    : LỖI (" . $e->getMessage() . ")\n";
    }
} else {
    echo "   Kết nối Redis Server    : BỎ QUA (EXTENSION DISABLED)\n";
}

echo "\n";

// 2. Kiểm thử Rate Limit thông thường
echo "2. Chạy thử 3 requests liên tiếp (Giới hạn: 2 requests / 10 giây)...\n";
$key = 'test_limit_' . time() . '_' . rand(1000, 9999);

$r1 = RateLimitService::check($key, 2, 10);
echo "   Request 1: " . ($r1 ? "ĐƯỢC PHÉP (PASS)" : "BỊ CHẶN (BLOCKED)") . "\n";

$r2 = RateLimitService::check($key, 2, 10);
echo "   Request 2: " . ($r2 ? "ĐƯỢC PHÉP (PASS)" : "BỊ CHẶN (BLOCKED)") . "\n";

$r3 = RateLimitService::check($key, 2, 10);
echo "   Request 3: " . ($r3 ? "ĐƯỢC PHÉP (PASS)" : "BỊ CHẶN (BLOCKED)") . " -> Mong đợi: BỊ CHẶN\n";

echo "\n";

// 3. Kiểm thử cơ chế Fault-Tolerance / Fallback khi Redis bị lỗi
echo "3. Kiểm thử cơ chế Fault-Tolerance (Giả lập Redis xảy ra sự cố)...\n";
echo "   Chúng ta sẽ thay đổi cấu hình kết nối Redis sang một IP không tồn tại (10.255.255.255) và kiểm tra xem hệ thống có tự động fallback về File-based driver mà không gây crash hay không...\n";

// Định nghĩa lại cấu hình Redis ảo bị lỗi bằng cách thay đổi giá trị thuộc tính tĩnh thông qua Reflection hoặc chạy độc lập.
// Để đơn giản và chính xác nhất, chúng ta sẽ mô phỏng việc mất kết nối bằng cách sử dụng Reflection để set temporary properties trong class RateLimitService.
try {
    $ref = new ReflectionClass('RateLimitService');
    
    // Reset attempt flag để bắt đầu thử lại
    $propAttempted = $ref->getProperty('redisAttempted');
    $propAttempted->setAccessible(true);
    $propAttempted->setValue(null, false);
    
    // Tạo một instance Redis ảo cố tình lỗi bằng cách thay thế host sang IP chết
    // Do REDIS_HOST là hằng số, ta có thể tạm thời thay đổi giá trị cache của redisInstance thành null và set redisAttempted = true để ép buộc bypass Redis.
    // Hoặc chạy một hàm test riêng biệt mà ta phá hủy kết nối Redis.
    // Cách sạch nhất: Chúng ta thay đổi biến môi trường động trong PHP bằng putenv và xóa cached instance để ép re-connect sang host chết:
    putenv("REDIS_HOST=10.255.255.255");
    putenv("REDIS_PORT=9999");
    
    // Reset cached properties
    $propInstance = $ref->getProperty('redisInstance');
    $propInstance->setAccessible(true);
    $propInstance->setValue(null, null);
    
    $propAttempted->setValue(null, false);
    
    echo "   [MÔ PHỎNG] Đã chuyển REDIS_HOST sang 10.255.255.255 (IP chết, timeout 1s).\n";
    echo "   Đang gửi 1 request mới...\n";
    
    $startTime = microtime(true);
    $fallbackKey = 'test_fallback_' . time() . '_' . rand(1000, 9999);
    $rFallback = RateLimitService::check($fallbackKey, 2, 10);
    $duration = microtime(true) - $startTime;
    
    echo "   Kết quả request với IP chết: " . ($rFallback ? "ĐƯỢC PHÉP (PASS - Fallback thành công)" : "BỊ CHẶN (BLOCKED)") . "\n";
    echo "   Thời gian xử lý: " . number_format($duration, 4) . " giây (Mong đợi khoảng 1.0 - 1.5 giây do timeout kết nối).\n";
    
    // Kiểm tra xem file log có ghi nhận cảnh báo kết nối không
    $logDir = __DIR__ . '/../logs/rate_limits';
    $fileCreated = false;
    foreach (glob($logDir . '/*.json') as $file) {
        if (strpos($file, $fallbackKey) !== false) {
            $fileCreated = true;
            break;
        }
    }
    echo "   Xác thực File-based fallback: " . ($fileCreated ? "THÀNH CÔNG (Đã tự động tạo file JSON thay thế)" : "THẤT BẠI") . "\n";
    
} catch (Throwable $e) {
    echo "   LỖI khi chạy kịch bản mô phỏng fallback: " . $e->getMessage() . "\n";
}

echo "\n=== KIỂM THỬ HOÀN TẤT ===\n";
