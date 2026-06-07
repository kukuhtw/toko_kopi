<?php

declare(strict_types=1);

namespace KopiBot\Tests\Unit;

use KopiBot\Core\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DatabaseConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        DatabaseConnection::reset();
    }

    public function testGetInstanceReturnsCachedPdoWhenAlreadyInitialized(): void
    {
        $reflection = new ReflectionClass(DatabaseConnection::class);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);

        $pdo = new PDO('sqlite::memory:');
        $pdoProperty->setValue(null, $pdo);

        self::assertSame($pdo, DatabaseConnection::getInstance());
    }

    public function testResetClearsCachedPdoInstance(): void
    {
        $reflection = new ReflectionClass(DatabaseConnection::class);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue(null, new PDO('sqlite::memory:'));

        DatabaseConnection::reset();

        self::assertNull($pdoProperty->getValue());
    }
}
