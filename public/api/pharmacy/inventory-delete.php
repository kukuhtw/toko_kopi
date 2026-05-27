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
         SET is_active = 0
         WHERE id = :id'
    );

    $stmt->execute([
        ':id' => (int)$payload['id'],
    ]);

    echo json_encode([
        'success' => true,
        'deleted_rows' => $stmt->rowCount(),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
