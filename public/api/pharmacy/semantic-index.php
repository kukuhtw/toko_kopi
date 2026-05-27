<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    $pdo = Database::getInstance();

    $stmt = $pdo->query(
        "SELECT mi.id, mi.name, mi.description,
                pm.generic_name,
                pm.manufacturer,
                pm.dosage
         FROM menu_items mi
         LEFT JOIN pharmacy_product_metadata pm ON pm.menu_item_id = mi.id"
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $index = [];

    foreach ($rows as $row) {
        $index[] = [
            'id' => $row['id'],
            'text' => implode(' ', [
                $row['name'],
                $row['description'],
                $row['generic_name'],
                $row['manufacturer'],
                $row['dosage'],
            ])
        ];
    }

    $path = dirname(__DIR__, 3) . '/storage/pharmacy/semantic-index.json';

    file_put_contents($path, json_encode($index, JSON_PRETTY_PRINT));

    echo json_encode([
        'success' => true,
        'indexed_rows' => count($index),
        'index_path' => $path,
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
