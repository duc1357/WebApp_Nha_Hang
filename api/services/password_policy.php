<?php

final class PasswordPolicy
{
    public const MESSAGE = 'Mật khẩu cần tối thiểu 8 ký tự, có chữ hoa và số.';

    public static function isValid(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/\d/', $password);
    }
}
