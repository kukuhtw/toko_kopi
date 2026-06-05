<?php

declare(strict_types=1);

namespace KopiBot\Core;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LoggerFactory
{
    public static function make(string $channel = 'app'): Logger
    {
        $logger = new Logger($channel);
        $logDir = storage_path('logs');

        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $logger->pushHandler(new StreamHandler($logDir . '/' . $channel . '.log'));

        return $logger;
    }
}
