<?php
require_once __DIR__ . '/auth_check_api.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/config/db.php';

$checks = [];

try {
    $conn = getDbConnection();
    $conn->query('SELECT 1');
    $conn->close();
    $checks['database'] = ['ok' => true, 'message' => 'Connected'];
} catch (Throwable $e) {
    $checks['database'] = ['ok' => false, 'message' => 'Connection failed'];
}

$logDir = ROOT_PATH . '/logs';
$checks['logs_writable'] = [
    'ok' => is_dir($logDir) && is_writable($logDir),
    'message' => is_dir($logDir) && is_writable($logDir) ? 'Writable' : 'Not writable',
];

$checks['rate_limit_store'] = [
    'ok' => is_dir($logDir . '/rate_limits') && is_writable($logDir . '/rate_limits'),
    'message' => is_dir($logDir . '/rate_limits') && is_writable($logDir . '/rate_limits') ? 'Writable' : 'Not writable',
];

$migrationOk = false;
try {
    $conn = getDbConnection();
    $res = $conn->query("SHOW TABLES LIKE '_migrations'");
    $migrationOk = $res && $res->num_rows > 0;
    $conn->close();
} catch (Throwable $e) {
    $migrationOk = false;
}

$checks['migrations_table'] = [
    'ok' => $migrationOk,
    'message' => $migrationOk ? 'Present' : 'Missing',
];

$checks['php_version'] = [
    'ok' => version_compare(PHP_VERSION, '8.3.0', '>='),
    'message' => PHP_VERSION,
];

$ok = !in_array(false, array_column($checks, 'ok'), true);

ResponseService::success([
    'healthy' => $ok,
    'checks' => $checks,
    'generated_at' => date('c'),
]);
