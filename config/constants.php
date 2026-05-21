<?php
// config/constants.php

require_once __DIR__ . '/env_loader.php';

define('ROOT_PATH', dirname(__DIR__));

define('BASE_URL', env('BASE_URL', 'http://restaurant.test'));

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'duong_bau_restaurant'));

define('SEPAY_WEBHOOK_TOKEN', env('SEPAY_WEBHOOK_TOKEN', ''));

define('SEPAY_VA_ACCOUNT', env('SEPAY_VA_ACCOUNT', ''));
define('SEPAY_BANK_NAME', env('SEPAY_BANK_NAME', 'MBBank'));

define('JWT_SECRET', env('JWT_SECRET', ''));
define('JWT_TTL_SECONDS', (int) env('JWT_TTL_SECONDS', 604800));
define('ADMIN_EMAIL', env('ADMIN_EMAIL', env('MAIL_USER', 'nhahangcomqueduongbau@gmail.com')));

// Redis Configuration
define('REDIS_HOST', env('REDIS_HOST', '127.0.0.1'));
define('REDIS_PORT', (int) env('REDIS_PORT', 6379));
define('REDIS_PASS', env('REDIS_PASS', ''));
define('REDIS_DB', (int) env('REDIS_DB', 0));
define('REDIS_ENABLED', filter_var(env('REDIS_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN));

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/session_config.php';
}
