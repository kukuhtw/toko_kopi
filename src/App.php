<?php

declare(strict_types=1);

namespace KopiBot;

use Dotenv\Dotenv;
use KopiBot\Core\ExceptionHandler;

class App
{
    private static bool $booted = false;

    public static function boot(string $basePath): void
    {
        if (self::$booted) {
            return;
        }

        self::loadEnvironment($basePath);
        self::registerExceptionHandler();

        self::$booted = true;
    }

    private static function loadEnvironment(string $basePath): void
    {
        if (!class_exists(Dotenv::class)) {
            return;
        }

        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->safeLoad();
    }

    private static function registerExceptionHandler(): void
    {
        if (!class_exists(ExceptionHandler::class)) {
            return;
        }

        ExceptionHandler::register();
    }
}
