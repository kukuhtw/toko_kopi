<?php

declare(strict_types=1);

namespace App\Plugin;

/**
 * Engine action/filter untuk sistem plugin KopiBot.
 *
 * Action  → plugin bereaksi terhadap event (tidak mengembalikan nilai).
 * Filter  → plugin memodifikasi nilai yang dilewatkan (harus mengembalikan nilai).
 *
 * Contoh penggunaan:
 *   // Di plugin:
 *   HookManager::addAction('order.created', [$this, 'kirimNotif']);
 *   HookManager::addFilter('cart.total',    [$this, 'tambahBiaya']);
 *
 *   // Di kode inti:
 *   HookManager::doAction('order.created', $order);
 *   $total = HookManager::applyFilters('cart.total', $total, $cart);
 */
final class HookManager
{
    /** @var array<string, array<int, callable[]>> */
    private static array $actions = [];

    /** @var array<string, array<int, callable[]>> */
    private static array $filters = [];

    // ── Actions ─────────────────────────────────────────────────

    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        self::$actions[$hook][$priority][] = $callback;
    }

    public static function doAction(string $hook, mixed ...$args): void
    {
        if (empty(self::$actions[$hook])) {
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
        return !empty(self::$actions[$hook]);
    }

    public static function removeAction(string $hook, callable $callback, int $priority = 10): void
    {
        if (!isset(self::$actions[$hook][$priority])) {
            return;
        }

        self::$actions[$hook][$priority] = array_filter(
            self::$actions[$hook][$priority],
            fn($cb) => $cb !== $callback
        );
    }

    // ── Filters ─────────────────────────────────────────────────

    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        self::$filters[$hook][$priority][] = $callback;
    }

    /**
     * Terapkan semua filter pada $value secara berurutan.
     * Setiap callback menerima $value sebagai argumen pertama
     * dan harus mengembalikan nilai yang (boleh) telah dimodifikasi.
     */
    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (empty(self::$filters[$hook])) {
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
        return !empty(self::$filters[$hook]);
    }

    public static function removeFilter(string $hook, callable $callback, int $priority = 10): void
    {
        if (!isset(self::$filters[$hook][$priority])) {
            return;
        }

        self::$filters[$hook][$priority] = array_filter(
            self::$filters[$hook][$priority],
            fn($cb) => $cb !== $callback
        );
    }

    // ── Utility ─────────────────────────────────────────────────

    /** Reset semua hook — berguna untuk testing */
    public static function reset(): void
    {
        self::$actions = [];
        self::$filters = [];
    }

    /** Kembalikan semua registered hooks (untuk debugging) */
    public static function dump(): array
    {
        return [
            'actions' => array_map(fn($p) => array_map('count', $p), self::$actions),
            'filters' => array_map(fn($p) => array_map('count', $p), self::$filters),
        ];
    }
}
