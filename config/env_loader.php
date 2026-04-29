<?php
/**
 * env_loader.php
 * Load environment variables từ file .env
 * 
 * Sử dụng: require_once __DIR__ . '/env_loader.php';
 * Sau đó truy cập bằng: env('KEY') hoặc $_ENV['KEY']
 */

function loadEnv(string $filePath): void {
    if (!file_exists($filePath)) {
        // Trong production, throw exception để alert ngay
        if (!defined('APP_ENV') || APP_ENV !== 'development') {
            error_log("CRITICAL: .env file not found at: $filePath");
        }
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Bỏ qua comment
        if (str_starts_with(trim($line), '#')) {
            continue;
        }

        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Remove surrounding quotes nếu có
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Set vào $_ENV và putenv (không ghi đè nếu đã tồn tại từ server env)
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

/**
 * Lấy giá trị env với default fallback
 */
function env(string $key, mixed $default = null): mixed {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Auto-load .env từ root project
loadEnv(dirname(__DIR__) . '/.env');
