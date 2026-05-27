<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';
require_once dirname(__DIR__, 3) . '/app/Services/ShopeeSyncService.php';

use App\Config\Database;
use App\Services\ShopeeSyncService;

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!$payload) {
        throw new RuntimeException('Invalid webhook payload.');
    }

    $pdo = Database::getInstance();
    $service = new ShopeeSyncService($pdo);

    $service->logWebhook(
        $payload['code'] ?? $payload['event'] ?? 'unknown',
        $payload
    );

    echo json_encode([
        'success' => true,
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
