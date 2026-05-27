<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';
require_once dirname(__DIR__, 3) . '/app/Services/TiktokShopSyncService.php';

use App\Config\Database;
use App\Services\TiktokShopSyncService;

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!$payload) {
        throw new RuntimeException('Invalid TikTok Shop order payload.');
    }

    $pdo = Database::getInstance();
    $service = new TiktokShopSyncService($pdo);

    $service->saveOrder($payload);

    if (!empty($payload['event'])) {
        $service->logWebhook($payload['event'], $payload);
    }

    echo json_encode([
        'success' => true,
        'order_id' => $payload['order_id'] ?? $payload['id'] ?? null,
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
