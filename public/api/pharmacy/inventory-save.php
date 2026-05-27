<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Services\PharmacyInventoryService;

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!$payload) {
        throw new RuntimeException('Invalid payload');
    }

    $service = new PharmacyInventoryService();

    $stockId = $service->addStock([
        'branch_id' => (int)($payload['branch_id'] ?? 1),
        'menu_item_id' => (int)$payload['menu_item_id'],
        'variant_id' => $payload['variant_id'] ?? null,
        'sku' => (string)$payload['sku'],
        'batch_no' => $payload['batch_no'] ?? null,
        'expired_date' => $payload['expired_date'] ?? null,
        'stock_qty' => (float)$payload['stock_qty'],
        'minimum_stock_qty' => (float)($payload['minimum_stock_qty'] ?? 5),
        'unit' => (string)($payload['unit'] ?? 'pcs'),
        'rack_location' => $payload['rack_location'] ?? null,
    ]);

    echo json_encode([
        'success' => true,
        'stock_id' => $stockId,
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
