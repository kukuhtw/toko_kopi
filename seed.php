<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use KopiBot\Core\Database;

$db = Database::getConnection();
$files = glob(__DIR__ . '/database/seeders/*.sql') ?: [];
sort($files);

$results = [];

foreach ($files as $file) {
    try {
        $db->exec((string) file_get_contents($file));
        $results[] = ['seeder' => basename($file), 'status' => 'success'];
    } catch (Throwable $e) {
        $results[] = ['seeder' => basename($file), 'status' => 'failed', 'error' => $e->getMessage()];
        break;
    }
}

echo json_encode([
    'success' => true,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
