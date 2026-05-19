<?php

declare(strict_types=1);

namespace App\Helpers;

class Sanitize
{
    public static function string(mixed $val): string
    {
        return htmlspecialchars(trim((string) $val), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function int(mixed $val): int
    {
        return (int) filter_var($val, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function float(mixed $val): float
    {
        return (float) filter_var($val, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    public static function email(mixed $val): string|false
    {
        return filter_var(trim((string) $val), FILTER_VALIDATE_EMAIL);
    }

    public static function phone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', $phone);
    }

    public static function slug(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', trim($text));
        return $text;
    }

    public static function post(string $key, string $type = 'string'): mixed
    {
        $val = $_POST[$key] ?? null;
        if ($val === null) return null;
        return match ($type) {
            'int'   => self::int($val),
            'float' => self::float($val),
            'email' => self::email($val),
            default => self::string($val),
        };
    }

    public static function get(string $key, string $type = 'string'): mixed
    {
        $val = $_GET[$key] ?? null;
        if ($val === null) return null;
        return match ($type) {
            'int'   => self::int($val),
            'float' => self::float($val),
            default => self::string($val),
        };
    }
}
