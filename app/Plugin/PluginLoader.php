<?php

declare(strict_types=1);

namespace App\Plugin;

/**
 * Memuat dan mengaktifkan plugin dari direktori /plugins.
 *
 * Setiap plugin aktif harus:
 *   1. Terdaftar di plugins/plugins.json dengan "active": true
 *   2. Memiliki file plugins/{slug}/plugin.php yang me-return array metadata
 *   3. Class-nya mengimplementasikan PluginInterface
 */
final class PluginLoader
{
    private static string $pluginsDir  = '';
    private static string $configFile  = '';

    /** @var array<string, array{instance: PluginInterface, meta: array}> */
    private static array $loaded = [];

    private static bool $initialized = false;

    public static function init(string $pluginsDir): void
    {
        if (self::$initialized) {
            return;
        }

        self::$pluginsDir  = rtrim($pluginsDir, '/\\');
        self::$configFile  = self::$pluginsDir . '/plugins.json';
        self::$initialized = true;

        self::loadAll();
    }

    private static function loadAll(): void
    {
        if (!is_dir(self::$pluginsDir)) {
            return;
        }

        foreach (self::getActiveSlugs() as $slug) {
            self::loadPlugin($slug);
        }
    }

    private static function loadPlugin(string $slug): void
    {
        $entryFile = self::$pluginsDir . '/' . $slug . '/plugin.php';

        if (!file_exists($entryFile)) {
            error_log("PluginLoader: entry file not found for '{$slug}'");
            return;
        }

        try {
            $meta = require $entryFile;

            if (!is_array($meta) || !isset($meta['class'])) {
                error_log("PluginLoader: plugin.php must return array with 'class' key [{$slug}]");
                return;
            }

            $className = $meta['class'];

            if (!class_exists($className)) {
                error_log("PluginLoader: class '{$className}' not found [{$slug}]");
                return;
            }

            $plugin = new $className();

            if (!($plugin instanceof PluginInterface)) {
                error_log("PluginLoader: '{$className}' must implement PluginInterface [{$slug}]");
                return;
            }

            $plugin->register();

            self::$loaded[$slug] = [
                'instance' => $plugin,
                'meta'     => $meta,
            ];
        } catch (\Throwable $e) {
            error_log("PluginLoader: failed to load '{$slug}': " . $e->getMessage());
        }
    }

    /** Baca daftar slug plugin yang aktif dari plugins.json */
    private static function getActiveSlugs(): array
    {
        if (!file_exists(self::$configFile)) {
            return [];
        }

        $json = json_decode((string) file_get_contents(self::$configFile), true);

        if (!is_array($json)) {
            return [];
        }

        return array_keys(array_filter($json, fn($v) => ($v['active'] ?? false) === true));
    }

    // ── Public API ───────────────────────────────────────────────

    /** Semua plugin yang berhasil dimuat */
    public static function getLoaded(): array
    {
        return self::$loaded;
    }

    /** Cek apakah plugin dengan slug tertentu sudah dimuat */
    public static function isLoaded(string $slug): bool
    {
        return isset(self::$loaded[$slug]);
    }

    /** Ambil instance plugin berdasarkan slug */
    public static function get(string $slug): ?PluginInterface
    {
        return self::$loaded[$slug]['instance'] ?? null;
    }

    /** Kembalikan metadata semua plugin yang dimuat (untuk halaman admin) */
    public static function getMetaAll(): array
    {
        return array_map(fn($p) => $p['meta'], self::$loaded);
    }
}
