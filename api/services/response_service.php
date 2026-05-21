<?php

class ResponseService {
    public static function json(array $payload, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload);
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
