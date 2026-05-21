<?php
require_once __DIR__ . '/auth_check_api.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/logger_service.php';

$allowedChannels = ['app', 'auth', 'payment', 'security'];
$channel = trim((string)($_GET['channel'] ?? 'auth'));
$limit = (int)($_GET['limit'] ?? 100);

if (!in_array($channel, $allowedChannels, true)) {
    ResponseService::error('Invalid log channel', 422);
}

if ($limit < 1) {
    $limit = 100;
}
$limit = min($limit, 300);

function redactLogValue(mixed $value): mixed {
    if (is_array($value)) {
        $redacted = [];
        foreach ($value as $key => $child) {
            $lower = strtolower((string)$key);
            if (str_contains($lower, 'token') ||
                str_contains($lower, 'password') ||
                str_contains($lower, 'secret') ||
                str_contains($lower, 'authorization') ||
                str_contains($lower, 'cookie')) {
                $redacted[$key] = '[redacted]';
            } else {
                $redacted[$key] = redactLogValue($child);
            }
        }
        return $redacted;
    }

    return $value;
}

$entries = array_map(static function (array $entry): array {
    $entry['context'] = redactLogValue($entry['context'] ?? []);
    $entry['request'] = redactLogValue($entry['request'] ?? []);
    return $entry;
}, Logger::tail($channel, $limit));

ResponseService::success([
    'channel' => $channel,
    'limit' => $limit,
    'logs' => array_reverse($entries),
]);
