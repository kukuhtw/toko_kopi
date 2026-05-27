<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';
require_once dirname(__DIR__, 3) . '/app/Services/TiktokShopSyncService.php';

use App\Config\Database;
use App\Services\TiktokShopSyncService;

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!$payload || empty($payload['menu_item_id'])) {
        throw new RuntimeException('menu_item_id required.');
    }

    $pdo = Database::getInstance();
    $service = new TiktokShopSyncService($pdo);

    $service->mapProduct([
        'menu_item_id' => $payload['menu_item_id'],
        'local_sku' => $payload['local_sku'] ?? null,
        'local_barcode' => $payload['local_barcode'] ?? null,
        'tiktok_product_id' => $payload['tiktok_product_id'] ?? null,
        'tiktok_sku_id' => $payload['tiktok_sku_id'] ?? null,
        'sync_status' => $payload['sync_status'] ?? 'mapped',
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'TikTok Shop product mapping synced.',
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
