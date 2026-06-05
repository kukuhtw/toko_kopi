<?php

declare(strict_types=1);

namespace KopiBot\Core;

class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return env_value($key, $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default ? 'true' : 'false');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }
}
