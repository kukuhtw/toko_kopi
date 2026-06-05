<?php

declare(strict_types=1);

namespace KopiBot\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $driver = env_value('DB_DRIVER', 'mysql');
        $host = env_value('DB_HOST', '127.0.0.1');
        $port = env_value('DB_PORT', '3306');
        $database = env_value('DB_DATABASE', '');
        $username = env_value('DB_USERNAME', 'root');
        $password = env_value('DB_PASSWORD', '');

        if ($driver !== 'mysql') {
            throw new PDOException('Unsupported database driver: ' . $driver);
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $database
        );

        self::$connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connection;
    }
}
