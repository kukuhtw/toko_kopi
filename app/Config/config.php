<?php

declare(strict_types=1);

// ============================================================
// Legacy compatibility configuration
// ============================================================
// Composer runtime is now the main bootstrap path. This file is kept
// as a compatibility shim for legacy App\* classes while migration to
// KopiBot\* continues.

require_once dirname(__DIR__, 2) . '/config/runtime.php';

if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/app');
}

// Legacy App\* autoloader fallback. Composer handles KopiBot\*.
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

if (class_exists(\App\Plugin\PluginLoader::class)) {
    \App\Plugin\PluginLoader::init(BASE_PATH . '/plugins');
}
