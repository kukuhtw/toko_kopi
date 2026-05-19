<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    private static string $host     = DB_HOST;
    private static string $port     = DB_PORT;
    private static string $dbname   = DB_NAME;
    private static string $username = DB_USER;
    private static string $password = DB_PASS;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    self::$host,
                    self::$port,
                    self::$dbname
                );

                self::$instance = new PDO($dsn, self::$username, self::$password, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                error_log('[DB] Connection failed: ' . $e->getMessage());
                http_response_code(500);
                die(json_encode(['error' => 'Database connection failed']));
            }
        }

        return self::$instance;
    }

    // Prevent clone & unserialization
    private function __clone() {}
}
