<?php
// tests/test_smtp.php — Chỉ dùng để test, xóa sau khi xong!
define('ROOT_PATH', dirname(__DIR__));

// Load env parser
if (file_exists(ROOT_PATH . '/config/env.php')) {
    require_once ROOT_PATH . '/config/env.php';
} elseif (file_exists(ROOT_PATH . '/api/config.php')) {
    require_once ROOT_PATH . '/api/config.php';
} else {
    // Load .env manually
    foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, '=') !== false && $line[0] !== '#') {
            [$k, $v] = explode('=', $line, 2);
            putenv(trim($k) . '=' . trim($v));
        }
    }
    function env($k, $d = null) { return getenv($k) ?: $d; }
}

require_once ROOT_PATH . '/config/mail_config.php';
require_once ROOT_PATH . '/lib/SimpleSMTP.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "=== SMTP TEST ===\n";
echo "Host : " . MAIL_HOST . "\n";
echo "Port : " . MAIL_PORT . "\n";
echo "User : " . MAIL_USER . "\n";
echo "Pass : " . (defined('MAIL_PASS') && MAIL_PASS ? str_repeat('*', strlen(MAIL_PASS)) : '(empty)') . "\n";
echo "\n";

// Kiểm tra kết nối socket trước
echo "[1] Kiểm tra kết nối socket ssl://" . MAIL_HOST . ":" . MAIL_PORT . " ...\n";
$ctx = stream_context_create(['ssl' => [
    'verify_peer'       => false,
    'verify_peer_name'  => false,
    'allow_self_signed' => true,
]]);
$sock = @stream_socket_client('ssl://' . MAIL_HOST . ':' . MAIL_PORT, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
if ($sock) {
    $banner = fgets($sock, 512);
    echo "    ✅ Kết nối OK. Banner: " . trim($banner) . "\n";
    fclose($sock);
} else {
    echo "    ❌ Không kết nối được: $errstr ($errno)\n";
    echo "    → Kiểm tra firewall hoặc ISP có chặn port 465 không.\n";
    exit;
}

// Gửi email test thực sự
echo "\n[2] Gửi email test tới " . MAIL_USER . " ...\n";
$smtp = new SimpleSMTP(MAIL_HOST, MAIL_USER, MAIL_PASS, MAIL_PORT);
$result = $smtp->send(
    MAIL_USER,
    'Test SMTP - Nhà Hàng Dượng Bầu',
    '<h2>✅ SMTP hoạt động!</h2><p>Email test gửi lúc ' . date('Y-m-d H:i:s') . '</p>',
    'Test SMTP'
);

if ($result) {
    echo "    ✅ Gửi email THÀNH CÔNG!\n";
    echo "    → Kiểm tra hộp thư: " . MAIL_USER . "\n";
} else {
    echo "    ❌ Gửi email THẤT BẠI!\n";
    echo "    → Lỗi: " . $smtp->error . "\n";
}
