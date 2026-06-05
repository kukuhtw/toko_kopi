<?php

declare(strict_types=1);

namespace KopiBot\Core;

class BranchContext
{
    private static ?array $branch = null;

    public static function set(array $branch): void
    {
        self::$branch = $branch;
    }

    public static function get(): ?array
    {
        return self::$branch;
    }

    public static function id(): ?int
    {
        return isset(self::$branch['id']) ? (int) self::$branch['id'] : null;
    }
}
