<?php
// config/mail_config.php
// Credentials được load từ .env, không hardcode tại đây

// Đảm bảo env_loader đã được gọi (thông qua constants.php)
if (!function_exists('env')) {
    require_once __DIR__ . '/env_loader.php';
}

define('MAIL_HOST',      env('MAIL_HOST',      'smtp.gmail.com'));
define('MAIL_USER',      env('MAIL_USER',      ''));
define('MAIL_PASS',      env('MAIL_PASS',      ''));
define('MAIL_PORT',      (int) env('MAIL_PORT', 465));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Nha Hang Duong Bau'));
