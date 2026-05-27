<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;
use App\Services\PharmacyEscposPrinterService;

header('Content-Type: text/plain');

try {
    $invoiceNo = trim((string)($_GET['invoice_no'] ?? ''));

    if ($invoiceNo === '') {
        throw new RuntimeException('invoice_no is required.');
    }

    $pdo = Database::getInstance();

    $saleStmt = $pdo->prepare(
        'SELECT *
         FROM pharmacy_pos_sales
         WHERE invoice_no = :invoice_no
         LIMIT 1'
    );

    $saleStmt->execute([
        ':invoice_no' => $invoiceNo,
    ]);

    $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        throw new RuntimeException('Invoice not found.');
    }

    $itemStmt = $pdo->prepare(
        'SELECT item_name, qty, unit_price, total_price
         FROM pharmacy_pos_sale_items
         WHERE sale_id = :sale_id
         ORDER BY id ASC'
    );

    $itemStmt->execute([
        ':sale_id' => (int)$sale['id'],
    ]);

    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    $service = new PharmacyEscposPrinterService();

    echo $service->generateReceipt($sale, $items);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Receipt error: ' . $e->getMessage();
}
