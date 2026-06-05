<?php

declare(strict_types=1);

namespace KopiBot\Core;

use PDO;
use PDOException;
use RuntimeException;

final class DatabaseConnection
{
    private static ?PDO $pdo = null;

    public static function getInstance(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) env_value('DB_HOST', '127.0.0.1');
        $port = (string) env_value('DB_PORT', '3306');
        $database = (string) env_value('DB_DATABASE', env_value('DB_NAME', 'kopibot'));
        $username = (string) env_value('DB_USERNAME', env_value('DB_USER', 'root'));
        $password = (string) env_value('DB_PASSWORD', env_value('DB_PASS', ''));
        $charset = (string) env_value('DB_CHARSET', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);

        try {
            self::$pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to connect to database: ' . $exception->getMessage(), 0, $exception);
        }

        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
