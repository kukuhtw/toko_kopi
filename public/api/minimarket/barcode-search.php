<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    $q = trim((string)($_GET['q'] ?? $_GET['barcode'] ?? $_GET['sku'] ?? ''));

    if ($q === '') {
        throw new RuntimeException('barcode, sku, or q is required.');
    }

    $pdo = Database::getInstance();

    $stmt = $pdo->prepare(
        'SELECT mi.id, mi.name, mi.description, mi.price,
                mm.sku, mm.barcode, mm.brand, mm.category, mm.unit, mm.pack_size,
                COALESCE(SUM(ms.qty), 0) AS stock_qty
         FROM menu_items mi
         JOIN minimarket_product_metadata mm ON mm.menu_item_id = mi.id
         LEFT JOIN minimarket_inventory_stock ms ON ms.menu_item_id = mi.id
         WHERE mm.barcode = :exact
            OR mm.sku = :exact
            OR LOWER(mi.name) LIKE :like_q
         GROUP BY mi.id, mi.name, mi.description, mi.price, mm.sku, mm.barcode, mm.brand, mm.category, mm.unit, mm.pack_size
         LIMIT 20'
    );

    $stmt->execute([
        ':exact' => $q,
        ':like_q' => '%' . strtolower($q) . '%',
    ]);

    echo json_encode([
        'success' => true,
        'query' => $q,
        'results' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
