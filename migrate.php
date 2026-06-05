<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use KopiBot\Core\MigrationRunner;

$runner = new MigrationRunner();
$results = $runner->run(__DIR__ . '/database/migrations');

echo json_encode([
    'success' => true,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
