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
define('SEPAY_BANK_NAME', env('SEPAY_BANK_NAME', 'MBBank'));
