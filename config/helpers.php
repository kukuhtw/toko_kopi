<?php

declare(strict_types=1);

if (!function_exists('env_value')) {
    function env_value(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $basePath = dirname(__DIR__) . '/storage';

        return $path === '' ? $basePath : $basePath . '/' . ltrim($path, '/');
    }
}
