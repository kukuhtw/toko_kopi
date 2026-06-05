<?php

declare(strict_types=1);

namespace KopiBot\Core;

class TenantContext
{
    private static ?array $tenant = null;

    public static function set(array $tenant): void
    {
        self::$tenant = $tenant;
    }

    public static function get(): ?array
    {
        return self::$tenant;
    }

    public static function id(): ?int
    {
        return isset(self::$tenant['id']) ? (int) self::$tenant['id'] : null;
    }
}
