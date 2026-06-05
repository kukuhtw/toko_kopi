<?php

declare(strict_types=1);

namespace KopiBot\Core;

final class PluginLoader
{
    private static string $pluginsDir = '';
    private static string $configFile = '';
    private static array $loaded = [];
    private static bool $initialized = false;

    public static function init(string $pluginsDir): void
    {
        if (self::$initialized) {
            return;
        }

        self::$pluginsDir = rtrim($pluginsDir, '/\\');
        self::$configFile = self::$pluginsDir . '/plugins.json';
        self::$initialized = true;

        self::loadAll();
    }

    public static function getLoaded(): array
    {
        return self::$loaded;
    }

    public static function isLoaded(string $slug): bool
    {
        return isset(self::$loaded[$slug]);
    }

    public static function get(string $slug): ?object
    {
        return self::$loaded[$slug]['instance'] ?? null;
    }

    public static function getMetaAll(): array
    {
        return array_map(static fn (array $plugin): array => $plugin['meta'], self::$loaded);
    }

    public static function reset(): void
    {
        self::$pluginsDir = '';
        self::$configFile = '';
        self::$loaded = [];
        self::$initialized = false;
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

        if (!is_file($entryFile)) {
            error_log("PluginLoader: entry file not found for '{$slug}'");
            return;
        }

        try {
            $meta = require $entryFile;

            if (!is_array($meta) || !isset($meta['class'])) {
                error_log("PluginLoader: plugin.php must return array with 'class' key [{$slug}]");
                return;
            }

            $className = (string) $meta['class'];

            if (!class_exists($className)) {
                error_log("PluginLoader: class '{$className}' not found [{$slug}]");
                return;
            }

            $plugin = new $className();

            if (!method_exists($plugin, 'register')) {
                error_log("PluginLoader: '{$className}' must provide register() [{$slug}]");
                return;
            }

            $plugin->register();

            self::$loaded[$slug] = [
                'instance' => $plugin,
                'meta' => $meta,
            ];
        } catch (\Throwable $exception) {
            error_log("PluginLoader: failed to load '{$slug}': " . $exception->getMessage());
        }
    }

    private static function getActiveSlugs(): array
    {
        if (!is_file(self::$configFile)) {
            return [];
        }

        $json = json_decode((string) file_get_contents(self::$configFile), true);

        if (!is_array($json)) {
            return [];
        }

        return array_keys(array_filter(
            $json,
            static fn (mixed $plugin): bool => is_array($plugin) && ($plugin['active'] ?? false) === true
        ));
    }
}
