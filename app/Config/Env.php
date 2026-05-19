<?php

declare(strict_types=1);

namespace App\Config;

final class Env
{
    /**
     * Load a .env file and populate $_ENV / putenv().
     * Values already set by the server environment are never overwritten.
     */
    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key   = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $value = self::unquote($value);

            // Never override values already provided by the server/OS
            if (!isset($_ENV[$key]) && getenv($key) === false) {
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }

    private static function unquote(string $value): string
    {
        // Strip trailing inline comment (only when value is not quoted)
        if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
            $commentPos = strpos($value, ' #');
            if ($commentPos !== false) {
                $value = trim(substr($value, 0, $commentPos));
            }
        }

        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last  = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, $len - 2);
            }
        }

        return $value;
    }
}
