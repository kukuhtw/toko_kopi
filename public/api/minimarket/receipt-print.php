<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    $invoiceNo = trim((string)($_GET['invoice_no'] ?? ''));

    if ($invoiceNo === '') {
        throw new RuntimeException('invoice_no required.');
    }

    $pdo = Database::getInstance();

    $saleStmt = $pdo->prepare(
        'SELECT * FROM minimarket_pos_sales WHERE invoice_no = :invoice_no LIMIT 1'
    );

    $saleStmt->execute([
        ':invoice_no' => $invoiceNo,
    ]);

    $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        throw new RuntimeException('Invoice not found.');
    }

    $itemStmt = $pdo->prepare(
        'SELECT * FROM minimarket_pos_sale_items WHERE sale_id = :sale_id ORDER BY id ASC'
    );

    $itemStmt->execute([
        ':sale_id' => $sale['id'],
    ]);

    echo json_encode([
        'success' => true,
        'invoice' => $sale,
        'items' => $itemStmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
