<?php

class ValidationService {
    public static function requiredString(array $data, string $key, string $message): string {
        $value = trim((string)($data[$key] ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException($message, 400);
        }
        return $value;
    }

    public static function optionalString(array $data, string $key): ?string {
        if (!isset($data[$key])) {
            return null;
        }

        $value = trim((string)$data[$key]);
        return $value === '' ? null : $value;
    }

    public static function email(string $value, string $message): string {
        $email = trim($value);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException($message, 422);
        }
        return $email;
    }

    public static function phone(string $value, string $message): string {
        $phone = trim($value);
        if (!preg_match('/^0[0-9]{9}$/', $phone)) {
            throw new InvalidArgumentException($message, 422);
        }
        return $phone;
    }

    public static function date(string $value, string $message): string {
        $date = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException($message, 422);
        }
        return $date;
    }

    public static function time(string $value, string $message): string {
        $time = trim($value);
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new InvalidArgumentException($message, 422);
        }
        return $time;
    }

    public static function intRange(mixed $value, int $min, int $max, string $message): int {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException($message, 422);
        }

        $intValue = (int)$value;
        if ($intValue < $min || $intValue > $max) {
            throw new InvalidArgumentException($message, 422);
        }

        return $intValue;
    }

    public static function enum(string $value, array $allowed, string $message): string {
        $normalized = trim($value);
        if (!in_array($normalized, $allowed, true)) {
            throw new InvalidArgumentException($message, 422);
        }
        return $normalized;
    }
}
