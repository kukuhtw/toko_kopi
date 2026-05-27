<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';
require_once dirname(__DIR__, 3) . '/app/Services/ShopeeSyncService.php';

use App\Config\Database;
use App\Services\ShopeeSyncService;

header('Content-Type: application/json');

try {
    $payload = [
        'shop_id' => $_GET['shop_id'] ?? null,
        'merchant_name' => $_GET['merchant_name'] ?? null,
        'access_token' => $_GET['access_token'] ?? null,
        'refresh_token' => $_GET['refresh_token'] ?? null,
        'expire_in' => $_GET['expire_in'] ?? null,
        'refresh_token_expire_in' => $_GET['refresh_token_expire_in'] ?? null,
    ];

    if (empty($payload['shop_id'])) {
        throw new RuntimeException('shop_id missing.');
    }

    $pdo = Database::getInstance();
    $service = new ShopeeSyncService($pdo);
    $service->saveShopToken($payload);

    echo json_encode([
        'success' => true,
        'shop_id' => $payload['shop_id'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
