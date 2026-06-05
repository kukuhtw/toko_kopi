<?php

declare(strict_types=1);

namespace KopiBot\Core;

final class HookManager
{
    private static array $actions = [];
    private static array $filters = [];

    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        self::$actions[$hook][$priority][] = $callback;
    }

    public static function doAction(string $hook, mixed ...$args): void
    {
        if (!isset(self::$actions[$hook])) {
            return;
        }

        ksort(self::$actions[$hook]);

        foreach (self::$actions[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                $callback(...$args);
            }
        }
    }

    public static function hasAction(string $hook): bool
    {
        return isset(self::$actions[$hook]) && self::$actions[$hook] !== [];
    }

    public static function removeAction(string $hook, callable $callback, int $priority = 10): void
    {
        if (!isset(self::$actions[$hook][$priority])) {
            return;
        }

        self::$actions[$hook][$priority] = array_values(array_filter(
            self::$actions[$hook][$priority],
            static fn (callable $registered): bool => $registered !== $callback
        ));
    }

    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        self::$filters[$hook][$priority][] = $callback;
    }

    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (!isset(self::$filters[$hook])) {
            return $value;
        }

        ksort(self::$filters[$hook]);

        foreach (self::$filters[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = $callback($value, ...$args);
            }
        }

        return $value;
    }

    public static function hasFilter(string $hook): bool
    {
        return isset(self::$filters[$hook]) && self::$filters[$hook] !== [];
    }

    public static function removeFilter(string $hook, callable $callback, int $priority = 10): void
    {
        if (!isset(self::$filters[$hook][$priority])) {
            return;
        }

        self::$filters[$hook][$priority] = array_values(array_filter(
            self::$filters[$hook][$priority],
            static fn (callable $registered): bool => $registered !== $callback
        ));
    }

    public static function clear(): void
    {
        self::reset();
    }

    public static function reset(): void
    {
        self::$actions = [];
        self::$filters = [];
    }

    public static function dump(): array
    {
        return [
            'actions' => array_map(static fn (array $priorities): array => array_map('count', $priorities), self::$actions),
            'filters' => array_map(static fn (array $priorities): array => array_map('count', $priorities), self::$filters),
        ];
    }
}
