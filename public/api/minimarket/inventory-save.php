<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';
require_once dirname(__DIR__, 3) . '/app/Services/MinimarketInventoryService.php';

use App\Config\Database;
use App\Services\MinimarketInventoryService;

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!$payload || empty($payload['menu_item_id']) || empty($payload['qty'])) {
        throw new RuntimeException('menu_item_id and qty are required.');
    }

    $pdo = Database::getInstance();
    $service = new MinimarketInventoryService($pdo);

    $stockId = $service->stockIn($payload);

    echo json_encode([
        'success' => true,
        'stock_id' => $stockId,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
