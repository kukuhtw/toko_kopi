<?php

declare(strict_types=1);

namespace App\Plugin;

use KopiBot\Core\PluginLoader as ComposerPluginLoader;

final class PluginLoader
{
    public static function init(string $pluginsDir): void
    {
        ComposerPluginLoader::init($pluginsDir);
    }

    public static function getLoaded(): array
    {
        return ComposerPluginLoader::getLoaded();
    }

    public static function isLoaded(string $slug): bool
    {
        return ComposerPluginLoader::isLoaded($slug);
    }

    public static function get(string $slug): ?object
    {
        return ComposerPluginLoader::get($slug);
    }

    public static function getMetaAll(): array
    {
        return ComposerPluginLoader::getMetaAll();
    }

    public static function reset(): void
    {
        ComposerPluginLoader::reset();
    }
}
