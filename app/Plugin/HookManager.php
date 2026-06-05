<?php

declare(strict_types=1);

namespace App\Plugin;

use KopiBot\Core\HookManager as ComposerHookManager;

final class HookManager
{
    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        ComposerHookManager::addAction($hook, $callback, $priority);
    }

    public static function doAction(string $hook, mixed ...$args): void
    {
        ComposerHookManager::doAction($hook, ...$args);
    }

    public static function hasAction(string $hook): bool
    {
        return ComposerHookManager::hasAction($hook);
    }

    public static function removeAction(string $hook, callable $callback, int $priority = 10): void
    {
        ComposerHookManager::removeAction($hook, $callback, $priority);
    }

    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        ComposerHookManager::addFilter($hook, $callback, $priority);
    }

    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return ComposerHookManager::applyFilters($hook, $value, ...$args);
    }

    public static function hasFilter(string $hook): bool
    {
        return ComposerHookManager::hasFilter($hook);
    }

    public static function removeFilter(string $hook, callable $callback, int $priority = 10): void
    {
        ComposerHookManager::removeFilter($hook, $callback, $priority);
    }

    public static function reset(): void
    {
        ComposerHookManager::reset();
    }

    public static function dump(): array
    {
        return ComposerHookManager::dump();
    }
}
