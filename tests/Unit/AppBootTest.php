<?php

declare(strict_types=1);

namespace KopiBot\Tests\Unit;

use KopiBot\App;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AppBootTest extends TestCase
{
    protected function setUp(): void
    {
        $reflection = new ReflectionClass(App::class);
        $booted = $reflection->getProperty('booted');
        $booted->setAccessible(true);
        $booted->setValue(null, false);
    }

    public function testBootMarksApplicationAsBooted(): void
    {
        App::boot(dirname(__DIR__, 2));

        $reflection = new ReflectionClass(App::class);
        $booted = $reflection->getProperty('booted');
        $booted->setAccessible(true);

        self::assertTrue($booted->getValue());
    }

    public function testBootIsIdempotent(): void
    {
        App::boot(dirname(__DIR__, 2));
        App::boot(dirname(__DIR__, 2));

        $reflection = new ReflectionClass(App::class);
        $booted = $reflection->getProperty('booted');
        $booted->setAccessible(true);

        self::assertTrue($booted->getValue());
    }
}
