<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!$payload || empty($payload['id'])) {
        throw new RuntimeException('Inventory stock id is required.');
    }

    $pdo = Database::getInstance();

    $stmt = $pdo->prepare(
        'UPDATE pharmacy_inventory_stock
         SET sku = :sku,
             batch_no = :batch_no,
             expired_date = :expired_date,
             stock_qty = :stock_qty,
             minimum_stock_qty = :minimum_stock_qty,
             unit = :unit,
             rack_location = :rack_location,
             is_active = :is_active
         WHERE id = :id'
    );

    $stmt->execute([
        ':id' => (int)$payload['id'],
        ':sku' => (string)$payload['sku'],
        ':batch_no' => $payload['batch_no'] ?? null,
        ':expired_date' => $payload['expired_date'] ?? null,
        ':stock_qty' => (float)($payload['stock_qty'] ?? 0),
        ':minimum_stock_qty' => (float)($payload['minimum_stock_qty'] ?? 5),
        ':unit' => (string)($payload['unit'] ?? 'pcs'),
        ':rack_location' => $payload['rack_location'] ?? null,
        ':is_active' => (int)($payload['is_active'] ?? 1),
    ]);

    echo json_encode([
        'success' => true,
        'updated_rows' => $stmt->rowCount(),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
