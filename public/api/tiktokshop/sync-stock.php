<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';
require_once dirname(__DIR__, 3) . '/app/Services/TiktokShopSyncService.php';

use App\Config\Database;
use App\Services\TiktokShopSyncService;

header('Content-Type: application/json');

try {
    $menuItemId = (int)($_GET['menu_item_id'] ?? 0);

    if ($menuItemId <= 0) {
        throw new RuntimeException('menu_item_id required.');
    }

    $pdo = Database::getInstance();
    $service = new TiktokShopSyncService($pdo);

    $payload = $service->buildRealtimeStockPayload($menuItemId);

    echo json_encode([
        'success' => true,
        'sync_type' => 'realtime-stock',
        'payload' => $payload,
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
