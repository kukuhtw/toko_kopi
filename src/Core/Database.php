<?php

declare(strict_types=1);

namespace KopiBot\Core;

use PDO;

/**
 * @deprecated Use DatabaseConnection::getInstance() instead.
 */
class Database
{
    public static function getConnection(): PDO
    {
        return DatabaseConnection::getInstance();
    }

    public static function reset(): void
    {
        DatabaseConnection::reset();
    }
}
