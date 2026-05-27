<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    $menuItemId = (int)($_GET['menu_item_id'] ?? 0);

    if ($menuItemId <= 0) {
        throw new RuntimeException('menu_item_id required.');
    }

    $pdo = Database::getInstance();

    $stmt = $pdo->prepare(
        'SELECT mi.id, mi.name,
                spm.shopee_item_id,
                spm.shopee_model_id,
                COALESCE(SUM(ms.qty), 0) AS total_stock
         FROM menu_items mi
         JOIN shopee_product_mapping spm ON spm.menu_item_id = mi.id
         LEFT JOIN minimarket_inventory_stock ms ON ms.menu_item_id = mi.id
         WHERE mi.id = :menu_item_id
         GROUP BY mi.id, mi.name, spm.shopee_item_id, spm.shopee_model_id
         LIMIT 1'
    );

    $stmt->execute([
        ':menu_item_id' => $menuItemId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Shopee mapping not found.');
    }

    echo json_encode([
        'success' => true,
        'payload' => [
            'item_id' => $row['shopee_item_id'],
            'model_id' => $row['shopee_model_id'],
            'normal_stock' => (int)$row['total_stock'],
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
