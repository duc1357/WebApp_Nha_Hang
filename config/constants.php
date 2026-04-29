<?php
// config/constants.php

// Load environment variables từ .env file (PHẢI load trước session)
require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/session_config.php';

// Định nghĩa đường dẫn gốc của dự án trên hệ thống file
define('ROOT_PATH', dirname(__DIR__));

// URL gốc (lấy từ .env)
define('BASE_URL', env('BASE_URL', 'http://restaurant.test'));

// Database Config (lấy từ .env)
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'duong_bau_restaurant'));

// SePay Webhook Token (lấy từ .env)
define('SEPAY_WEBHOOK_TOKEN', env('SEPAY_WEBHOOK_TOKEN', ''));

// SePay Payment Info (lấy từ .env)
define('SEPAY_VA_ACCOUNT', env('SEPAY_VA_ACCOUNT', ''));
define('SEPAY_BANK_NAME',  env('SEPAY_BANK_NAME',  'MBBank'));

// JWT Config (lấy từ .env)
// Tạo secret bằng lệnh: php -r "echo bin2hex(random_bytes(32));"
// QUAN TRỌNG: Phải là chuỗi ngẫu nhiên ít nhất 32 ký tự, KHÔNG hardcode!
define('JWT_SECRET',      env('JWT_SECRET',      ''));
define('JWT_TTL_SECONDS', (int) env('JWT_TTL_SECONDS', 604800)); // Mặc định: 7 ngày
