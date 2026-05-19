<?php

declare(strict_types=1);

namespace App\Helpers;

class Csrf
{
    public static function generate(): string
    {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function verify(string $token): bool
    {
        $stored = $_SESSION[CSRF_TOKEN_NAME] ?? '';
        if (empty($stored) || !hash_equals($stored, $token)) {
            return false;
        }
        // Rotate token after use
        unset($_SESSION[CSRF_TOKEN_NAME]);
        return true;
    }

    public static function field(): string
    {
        $token = self::generate();
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars(CSRF_TOKEN_NAME),
            htmlspecialchars($token)
        );
    }

    public static function requireValid(): void
    {
        $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!self::verify($token)) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }
    }
}
