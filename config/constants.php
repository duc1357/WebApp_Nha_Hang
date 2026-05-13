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

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/session_config.php';
}
