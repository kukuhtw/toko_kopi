<?php

declare(strict_types=1);

namespace App\Config;

use KopiBot\Core\DatabaseConnection;
use PDO;

class Database
{
    public static function getInstance(): PDO
    {
        return DatabaseConnection::getInstance();
    }

    public static function reset(): void
    {
        DatabaseConnection::reset();
    }

    private function __clone() {}
}
