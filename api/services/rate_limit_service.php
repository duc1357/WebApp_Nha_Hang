<?php
// api/services/rate_limit_service.php
require_once __DIR__ . '/../../config/constants.php';

class RateLimitService {
    // Thư mục lưu file rate limit (bên ngoài web root, được bảo vệ bởi .htaccess)
    private static string $storageDir = '';

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
     * Kiểm tra rate limit dựa trên IP thực của client.
     * Không thể bypass bằng cách xóa cookie hay đổi session.
     *
     * @param string $key       Định danh hành động (vd: 'login', 'register')
     * @param int    $maxRequests Số lần tối đa cho phép
     * @param int    $periodSeconds Cửa sổ thời gian (giây)
     * @return bool True = còn quota, False = đã vượt giới hạn
     */
    public static function check(string $key, int $maxRequests = 5, int $periodSeconds = 60): bool {
        $ip = self::getClientIp();
        // Hash IP để ẩn dữ liệu trong filesystem
        $fileKey  = $key . '_' . hash('sha256', $ip);
        $filePath = self::getStorageDir() . '/' . $fileKey . '.json';

        $now  = time();
        $data = ['count' => 0, 'start_time' => $now];

        // Đọc dữ liệu hiện tại với file lock
        if (file_exists($filePath)) {
            $fp = fopen($filePath, 'r+');
            if ($fp && flock($fp, LOCK_EX)) {
                $content = fread($fp, 512);
                $stored  = json_decode($content, true);
                if (is_array($stored)) {
                    // Reset nếu đã hết window
                    if ($now - $stored['start_time'] > $periodSeconds) {
                        $data = ['count' => 0, 'start_time' => $now];
                    } else {
                        $data = $stored;
                    }
                }
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }

        $data['count']++;

        // Ghi lại với lock
        file_put_contents($filePath, json_encode($data), LOCK_EX);

        // Dọn file cũ mỗi 100 request (xác suất thấp để không nặng hệ thống)
        if (rand(1, 100) === 1) {
            self::cleanOldFiles($periodSeconds * 2);
        }

        return $data['count'] <= $maxRequests;
    }

    /**
     * Lấy IP thực của client, xử lý proxy và Cloudflare.
     */
    private static function getClientIp(): string {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',   // Cloudflare
            $_SERVER['HTTP_X_FORWARDED_FOR']  ?? '',   // Reverse proxy
            $_SERVER['HTTP_X_REAL_IP']        ?? '',   // Nginx proxy
            $_SERVER['REMOTE_ADDR']           ?? '',
        ];

        foreach ($candidates as $ip) {
            // X-Forwarded-For có thể là danh sách IP ngăn cách bởi dấu phẩy
            $ip = trim(explode(',', $ip)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        // Fallback về REMOTE_ADDR nếu không tìm được public IP
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
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
