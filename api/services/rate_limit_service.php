<?php
// api/services/rate_limit_service.php
require_once __DIR__ . '/../../config/constants.php';

class RateLimitService {
    // Thư mục lưu file rate limit (bên ngoài web root, được bảo vệ bởi .htaccess)
    private static string $storageDir = '';
    private static ?Redis $redisInstance = null;
    private static bool $redisAttempted = false;

    private static function getStorageDir(): string {
        if (empty(self::$storageDir)) {
            self::$storageDir = ROOT_PATH . '/logs/rate_limits';
            if (!is_dir(self::$storageDir)) {
                mkdir(self::$storageDir, 0750, true);
            }
        }
        return self::$storageDir;
    }

    /**
     * Khởi tạo kết nối Redis an toàn (Fault-tolerant)
     */
    private static function getRedisInstance(): ?Redis {
        if (!defined('REDIS_ENABLED') || !REDIS_ENABLED) {
            return null;
        }

        if (self::$redisAttempted) {
            return self::$redisInstance;
        }

        self::$redisAttempted = true;

        if (!class_exists('Redis')) {
            return null;
        }

        try {
            $redis = new Redis();
            // Timeout kết nối ngắn (1.5 giây) để không chặn luồng xử lý chính của người dùng
            $connected = $redis->connect(REDIS_HOST, REDIS_PORT, 1.5);
            if (!$connected) {
                return null;
            }

            if (!empty(REDIS_PASS)) {
                $redis->auth(REDIS_PASS);
            }

            if (defined('REDIS_DB') && REDIS_DB !== 0) {
                $redis->select(REDIS_DB);
            }

            self::$redisInstance = $redis;
        } catch (Throwable $e) {
            // Không crash hệ thống, tự động log lỗi
            error_log('[Redis] Connection failed: ' . $e->getMessage());
            self::$redisInstance = null;
        }

        return self::$redisInstance;
    }

    /**
     * Kiểm tra rate limit dựa trên IP thực của client.
     * Hỗ trợ Driver Kép: Thử sử dụng Redis trước, tự động fallback về File-based nếu có lỗi.
     *
     * @param string $key       Định danh hành động (vd: 'login', 'register')
     * @param int    $maxRequests Số lần tối đa cho phép
     * @param int    $periodSeconds Cửa sổ thời gian (giây)
     * @return bool True = còn quota, False = đã vượt giới hạn
     */
    public static function check(string $key, int $maxRequests = 5, int $periodSeconds = 60): bool {
        $ip = self::getClientIp();
        $key = self::normalizeKey($key);

        // --- DRIVER 1: REDIS DRIVER (Tối ưu I/O memory, chống TOCTOU bằng transaction) ---
        try {
            $redis = self::getRedisInstance();
            if ($redis !== null) {
                $hashedIp = hash('sha256', $ip);
                $redisKey = "rate_limit:" . $key . ":" . $hashedIp;

                // Transaction nguyên tử
                $redis->multi();
                $redis->incr($redisKey);
                $redis->ttl($redisKey);
                $results = $redis->exec();

                if (is_array($results) && count($results) >= 2) {
                    $count = $results[0];
                    $ttl = $results[1];

                    // Nếu khóa mới được khởi tạo hoặc chưa có thời gian hết hạn (TTL <= 0)
                    if ($ttl === -1 || $ttl === false || $ttl === 0) {
                        $redis->expire($redisKey, $periodSeconds);
                    }

                    return $count <= $maxRequests;
                }
            }
        } catch (Throwable $e) {
            // Ghi log cảnh báo mức WARNING khi lỗi Redis và chạy tiếp luồng File-based
            error_log('[RateLimit] Redis error, falling back to File-based driver: ' . $e->getMessage());
        }

        // --- DRIVER 2: FILE-BASED DRIVER (Fallback an toàn, đã chống TOCTOU bằng flock) ---
        $fileKey  = $key . '_' . hash('sha256', $ip);
        $filePath = self::getStorageDir() . '/' . $fileKey . '.json';

        $now  = time();
        $data = ['count' => 0, 'start_time' => $now];

        $fp = fopen($filePath, 'c+');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                // Đọc toàn bộ nội dung file
                $content = '';
                while (!feof($fp)) {
                    $content .= fread($fp, 512);
                }
                $stored = json_decode(trim($content), true);

                if (is_array($stored)) {
                    // Reset nếu đã hết window
                    if ($now - $stored['start_time'] > $periodSeconds) {
                        $data = ['count' => 0, 'start_time' => $now];
                    } else {
                        $data = $stored;
                    }
                }

                $data['count']++;

                // Ghi đè dữ liệu mới dưới lock
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($data));
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        } else {
            // Fallback nếu không mở được file
            $data['count']++;
        }

        // Dọn file cũ mỗi 100 request (xác suất thấp để không nặng hệ thống)
        if (rand(1, 100) === 1) {
            self::cleanOldFiles($periodSeconds * 2);
        }

        return $data['count'] <= $maxRequests;
    }

    private static function normalizeKey(string $key): string {
        $key = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $key);
        $key = trim((string)$key, '._:-');
        return $key !== '' ? $key : 'default';
    }

    private static function getClientIp(): string {
        $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));

        if (self::shouldTrustProxyHeaders($remoteAddr)) {
            $forwarded = self::firstValidForwardedIp([
                $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
                $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
                $_SERVER['HTTP_X_REAL_IP'] ?? '',
            ]);

            if ($forwarded !== null) {
                return $forwarded;
            }
        }

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '0.0.0.0';
    }

    private static function shouldTrustProxyHeaders(string $remoteAddr): bool {
        if (!defined('TRUST_PROXY_HEADERS') || !TRUST_PROXY_HEADERS) {
            return false;
        }

        return self::isTrustedProxy($remoteAddr);
    }

    private static function isTrustedProxy(string $remoteAddr): bool {
        if (!filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return false;
        }

        $trusted = defined('TRUSTED_PROXY_IPS') ? TRUSTED_PROXY_IPS : '';
        $ips = array_filter(array_map('trim', explode(',', $trusted)));

        return in_array($remoteAddr, $ips, true);
    }

    private static function firstValidForwardedIp(array $headers): ?string {
        foreach ($headers as $header) {
            foreach (explode(',', (string)$header) as $candidate) {
                $ip = trim($candidate);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Xóa các file rate limit đã hết hạn (housekeeping).
     */
    private static function cleanOldFiles(int $olderThanSeconds): void {
        $dir = self::getStorageDir();
        $threshold = time() - $olderThanSeconds;
        foreach (glob($dir . '/*.json') as $file) {
            if (filemtime($file) < $threshold) {
                @unlink($file);
            }
        }
    }
}
