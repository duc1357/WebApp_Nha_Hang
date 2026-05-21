<?php
// api/services/logger_service.php
// Structured JSON Logger – Centralized logging với log rotation
//
// Tính năng:
//   - Log ra file JSON (mỗi dòng 1 JSON object – NDJSON format)
//   - Phân loại mức độ: DEBUG, INFO, WARNING, ERROR, CRITICAL
//   - Rotation tự động khi file vượt ngưỡng kích thước (mặc định 5MB)
//   - Giữ tối đa N file backup (mặc định 7)
//   - Context-aware: tự động đính kèm IP, request URI, user_id từ session
//
// Cách dùng:
//   Logger::info('User logged in', ['user_id' => 5]);
//   Logger::error('Payment failed', ['order_id' => 42, 'error' => $msg]);
//   Logger::critical('Webhook bypass attempt', ['ip' => $ip]);

class Logger
{
    // Mức độ log (theo RFC 5424)
    const DEBUG    = 'DEBUG';
    const INFO     = 'INFO';
    const WARNING  = 'WARNING';
    const ERROR    = 'ERROR';
    const CRITICAL = 'CRITICAL';

    // Cấu hình (có thể override bằng constants trong config)
    private static string $logDir      = '';
    private static int    $maxFileSize  = 5 * 1024 * 1024; // 5MB
    private static int    $maxBackups   = 7;                // Giữ 7 file backup

    /* =========================================
       INIT & CONFIGURATION
       ========================================= */

    /** Khởi tạo thư mục log (gọi tự động khi write lần đầu) */
    private static function init(): void
    {
        if (self::$logDir) return;

        // Mặc định lưu vào logs/ ngoài web root (đã block bởi .htaccess)
        self::$logDir = defined('ROOT_PATH')
            ? ROOT_PATH . '/logs'
            : dirname(__DIR__, 2) . '/logs';

        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }

    /* =========================================
       CORE WRITE
       ========================================= */

    /**
     * Ghi một log entry vào file theo channel.
     *
     * @param string $level    Mức độ (DEBUG|INFO|WARNING|ERROR|CRITICAL)
     * @param string $message  Thông điệp log
     * @param array  $context  Dữ liệu context thêm vào
     * @param string $channel  Tên file log (vd: 'app', 'auth', 'payment')
     */
    public static function write(
        string $level,
        string $message,
        array  $context = [],
        string $channel = 'app'
    ): void {
        self::init();

        $entry = json_encode([
            'timestamp' => date('Y-m-d\TH:i:sP'),
            'level'     => $level,
            'channel'   => $channel,
            'message'   => $message,
            'context'   => $context,
            'request'   => self::getRequestContext(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($entry === false) return;

        $file = self::$logDir . "/{$channel}.log";

        // Rotation trước khi ghi nếu file quá lớn
        self::rotateIfNeeded($file);

        // Ghi atomic với file lock (ngăn race condition)
        $fp = fopen($file, 'a');
        if ($fp) {
            flock($fp, LOCK_EX);
            fwrite($fp, $entry . PHP_EOL);
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        if ($level === self::CRITICAL) {
            self::sendCriticalAlertEmail($message, $context, $channel);
        }
    }

    /* =========================================
       CONVENIENCE METHODS
       ========================================= */

    public static function debug(string $msg, array $ctx = [], string $ch = 'app'): void
    {
        self::write(self::DEBUG, $msg, $ctx, $ch);
    }

    public static function info(string $msg, array $ctx = [], string $ch = 'app'): void
    {
        self::write(self::INFO, $msg, $ctx, $ch);
    }

    public static function warning(string $msg, array $ctx = [], string $ch = 'app'): void
    {
        self::write(self::WARNING, $msg, $ctx, $ch);
    }

    public static function error(string $msg, array $ctx = [], string $ch = 'app'): void
    {
        self::write(self::ERROR, $msg, $ctx, $ch);
    }

    public static function critical(string $msg, array $ctx = [], string $ch = 'app'): void
    {
        self::write(self::CRITICAL, $msg, $ctx, $ch);
    }

    /* =========================================
       DOMAIN-SPECIFIC HELPERS
       ========================================= */

    /** Log auth events (login, logout, register) */
    public static function auth(string $msg, array $ctx = []): void
    {
        self::write(self::INFO, $msg, $ctx, 'auth');
    }

    /** Log payment events */
    public static function payment(string $msg, array $ctx = [], string $level = self::INFO): void
    {
        self::write($level, $msg, $ctx, 'payment');
    }

    /** Log security events (rate limit, suspicious activity) */
    public static function security(string $msg, array $ctx = [], string $level = self::WARNING): void
    {
        self::write($level, $msg, $ctx, 'security');
    }

    /* =========================================
       LOG ROTATION
       ========================================= */

    /**
     * Rotate log file nếu vượt ngưỡng kích thước.
     * Pattern: app.log → app.log.1 → ... → app.log.7 (xóa file cũ nhất)
     */
    private static function rotateIfNeeded(string $file): void
    {
        if (!file_exists($file) || filesize($file) < self::$maxFileSize) return;

        // Xóa file backup cũ nhất
        $oldest = $file . '.' . self::$maxBackups;
        if (file_exists($oldest)) unlink($oldest);

        // Shift backup files: .6 → .7, .5 → .6, ...
        for ($i = self::$maxBackups - 1; $i >= 1; $i--) {
            $from = $file . '.' . $i;
            $to   = $file . '.' . ($i + 1);
            if (file_exists($from)) rename($from, $to);
        }

        // Rename current → .1
        rename($file, $file . '.1');
    }

    /* =========================================
       REQUEST CONTEXT
       ========================================= */

    /** Thu thập thông tin request để đính kèm vào mỗi log entry */
    private static function getRequestContext(): array
    {
        $ctx = [
            'ip'     => self::getClientIp(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri'    => $_SERVER['REQUEST_URI'] ?? 'N/A',
        ];

        // Thêm user_id nếu đã đăng nhập
        if (isset($_SESSION['user_id'])) {
            $ctx['user_id'] = $_SESSION['user_id'];
        }

        return $ctx;
    }

    /** Lấy IP thực của client (hỗ trợ Cloudflare/proxy) */
    private static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                // X-Forwarded-For có thể chứa nhiều IP, lấy IP đầu tiên
                $ip = explode(',', $_SERVER[$h])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return 'unknown';
    }

    /* =========================================
       LOG READER (Dùng trong admin dashboard)
       ========================================= */

    /**
     * Đọc N dòng cuối của file log (tail -n)
     *
     * @param string $channel  Tên channel ('app', 'auth', 'payment', 'security')
     * @param int    $lines    Số dòng muốn lấy
     * @return array           Mảng log entries (đã decode JSON)
     */
    public static function tail(string $channel = 'app', int $lines = 100): array
    {
        self::init();
        $file = self::$logDir . "/{$channel}.log";
        if (!file_exists($file)) return [];

        $content = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $recent  = array_slice($content, -$lines);

        return array_values(array_filter(
            array_map(fn($line) => json_decode($line, true), $recent)
        ));
    }

    /**
     * Gửi email cảnh báo an ninh/lỗi nghiêm trọng cho Admin (Có chống lặp vô tận)
     */
    private static function sendCriticalAlertEmail(string $message, array $context, string $channel): void
    {
        static $isSending = false;
        if ($isSending) return;
        $isSending = true;

        try {
            require_once __DIR__ . '/email_service.php';

            $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : (defined('MAIL_USER') ? MAIL_USER : '');
            if (empty($adminEmail)) {
                error_log("[Logger Alert] ADMIN_EMAIL or MAIL_USER not configured.");
                $isSending = false;
                return;
            }

            $subject = "🚨 [Cảnh Báo Hệ Thống] Critical Log Triggered - Nhà Hàng Dượng Bầu";
            $ip = self::getClientIp();
            $uri = $_SERVER['REQUEST_URI'] ?? 'N/A';
            $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
            $time = date('Y-m-d H:i:s');
            $contextStr = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #f3a0a0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);'>
                    <div style='background-color: #e74c3c; color: #ffffff; padding: 20px; text-align: center;'>
                        <h2 style='margin: 0; font-size: 22px; letter-spacing: 1px;'>🚨 CẢNH BÁO SỰ CỐ NGHIÊM TRỌNG</h2>
                    </div>
                    <div style='padding: 24px; background-color: #ffffff; color: #2c3e50; line-height: 1.6;'>
                        <p style='margin-top: 0;'>Chào Admin,</p>
                        <p>Hệ thống vừa phát hiện và ghi nhận một sự kiện nghiêm trọng (log cấp độ <strong>CRITICAL</strong>). Vui lòng kiểm tra ngay lập tức:</p>

                        <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                            <tr>
                                <td style='padding: 8px 0; font-weight: bold; width: 120px; border-bottom: 1px solid #f1f3f5;'>Thời gian:</td>
                                <td style='padding: 8px 0; border-bottom: 1px solid #f1f3f5;'>{$time}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; font-weight: bold; border-bottom: 1px solid #f1f3f5;'>Kênh log:</td>
                                <td style='padding: 8px 0; border-bottom: 1px solid #f1f3f5;'><span style='background:#f8d7da; color:#721c24; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:bold;'>{$channel}</span></td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; font-weight: bold; border-bottom: 1px solid #f1f3f5;'>Yêu cầu:</td>
                                <td style='padding: 8px 0; border-bottom: 1px solid #f1f3f5;'><strong>[{$method}]</strong> {$uri}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; font-weight: bold; border-bottom: 1px solid #f1f3f5;'>Địa chỉ IP:</td>
                                <td style='padding: 8px 0; border-bottom: 1px solid #f1f3f5;'><code>{$ip}</code></td>
                            </tr>
                        </table>

                        <div style='background-color: #fff5f5; border-left: 4px solid #e74c3c; padding: 16px; border-radius: 4px; margin-bottom: 24px;'>
                            <strong style='color: #c0392b; display: block; margin-bottom: 6px;'>Thông điệp sự cố:</strong>
                            <span style='font-size: 15px; font-weight: bold;'>{$message}</span>
                        </div>

                        <h3 style='font-size: 16px; border-bottom: 2px solid #eaedf1; padding-bottom: 8px; margin-top: 0;'>Dữ liệu Context đính kèm:</h3>
                        <pre style='background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; font-family: Courier New, monospace; font-size: 13px; overflow-x: auto; white-space: pre-wrap; word-break: break-all;'>{$contextStr}</pre>

                        <p style='color: #7f8c8d; font-size: 11px; margin-top: 30px; text-align: center; border-top: 1px solid #eaedf1; padding-top: 15px;'>
                            Email này được gửi tự động từ hệ thống giám sát thời gian thực của Nhà Hàng Cơm Quê Dượng Bầu.
                        </p>
                    </div>
                </div>
            ";

            EmailService::send($adminEmail, $subject, $body);
        } catch (Throwable $e) {
            // Chỉ ghi error_log của PHP, tuyệt đối không gọi Logger ghi lỗi tránh loop
            error_log("[Logger Critical Alert Email Error] " . $e->getMessage());
        } finally {
            $isSending = false;
        }
    }
}
