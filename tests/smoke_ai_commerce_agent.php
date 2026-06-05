<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\AI\CommerceAgent;

$message = (string) ($_GET['message'] ?? 'saya mau 2 cappuccino dan 1 croissant');
$result = (new CommerceAgent())->understand($message);

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
