<?php

class ResponseService {
    public static function json(array $payload, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        try {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('[ResponseService] JSON encode failed: ' . $e->getMessage());
            http_response_code(500);
            echo '{"success":false,"message":"Loi he thong. Vui long thu lai sau."}';
        }

        exit;
    }

    public static function success(array $data = [], int $status = 200): void {
        self::json(array_merge(['success' => true], $data), $status);
    }

    public static function error(string $message, int $status = 400, array $extra = []): void {
        self::json(array_merge([
            'success' => false,
            'message' => $message,
        ], $extra), $status);
    }
}
