<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

$barcode = trim((string)($_GET['barcode'] ?? ''));

if ($barcode === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Barcode is required.'
    ]);
    exit;
}

$pdo = Database::getInstance();

$stmt = $pdo->prepare(
    "SELECT s.*, mi.name AS product_name, pm.requires_prescription
     FROM pharmacy_inventory_stock s
     JOIN menu_items mi ON mi.id = s.menu_item_id
     LEFT JOIN pharmacy_product_metadata pm ON pm.menu_item_id = mi.id
     WHERE s.sku = :sku
     LIMIT 1"
);

$stmt->execute([':sku' => $barcode]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode([
        'success' => false,
        'message' => 'Product not found.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => $row
]);
