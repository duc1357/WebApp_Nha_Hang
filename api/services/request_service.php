<?php

class RequestService {
    public static function contentType(): string {
        return strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
    }

    public static function json(bool $required = false): array {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if ($required && (trim((string)$raw) === '' || !is_array($data))) {
            throw new InvalidArgumentException('Request body must be valid JSON', 400);
        }

        return is_array($data) ? $data : [];
    }

    public static function input(bool $required = false): array {
        if (str_contains(self::contentType(), 'application/json')) {
            return self::json($required);
        }

        if ($required && empty($_POST)) {
            throw new InvalidArgumentException('Request body is required', 400);
        }

        return $_POST;
    }
}
