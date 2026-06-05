<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

if (class_exists(Dotenv::class)) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}
