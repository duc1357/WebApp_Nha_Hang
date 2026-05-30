<?php

class CsvService {
    private const FORMULA_PREFIXES = ['=', '+', '-', '@'];

    public static function safeCell(mixed $value): mixed {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $text = (string)$value;
        $trimmedLeft = ltrim($text);
        if ($trimmedLeft !== '' && in_array($trimmedLeft[0], self::FORMULA_PREFIXES, true)) {
            return "'" . $text;
        }

        return $text;
    }

    public static function safeRow(array $row): array {
        return array_map([self::class, 'safeCell'], $row);
    }
}
