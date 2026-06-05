<?php

declare(strict_types=1);

namespace KopiBot\Core;

use Throwable;

class ExceptionHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);
    }

    public static function handle(Throwable $e): void
    {
        $debug = Config::bool('APP_DEBUG', false);

        Response::json([
            'success' => false,
            'message' => $debug ? $e->getMessage() : 'Internal server error',
            'trace' => $debug ? $e->getTraceAsString() : null,
        ], 500);
    }
}
