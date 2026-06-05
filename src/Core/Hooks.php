<?php

declare(strict_types=1);

namespace KopiBot\Core;

final class Hooks
{
    private static array $filters = [];

    public static function addFilter(string $name, callable $callback, int $priority = 10): void
    {
        self::$filters[$name][$priority][] = $callback;
    }

    public static function applyFilters(string $name, mixed $value, mixed ...$args): mixed
    {
        if (!isset(self::$filters[$name])) {
            return $value;
        }

        ksort(self::$filters[$name]);

        foreach (self::$filters[$name] as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = $callback($value, ...$args);
            }
        }

        return $value;
    }

    public static function hasFilter(string $name): bool
    {
        return isset(self::$filters[$name]) && self::$filters[$name] !== [];
    }

    public static function clear(): void
    {
        self::$filters = [];
    }
}
