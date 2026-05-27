<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';
require_once dirname(__DIR__, 3) . '/app/Services/MinimarketInventoryService.php';

use App\Config\Database;
use App\Services\MinimarketInventoryService;

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!$payload || empty($payload['items'])) {
        throw new RuntimeException('Invalid payload.');
    }

    $pdo = Database::getInstance();
    $service = new MinimarketInventoryService($pdo);

    $invoiceNo = 'MM-' . date('YmdHis');
    $branchId = (int)($payload['branch_id'] ?? 1);

    $subtotal = 0;

    foreach ($payload['items'] as $item) {
        $subtotal += ((float)$item['qty'] * (float)$item['price']);
    }

    $pdo->beginTransaction();

    $saleStmt = $pdo->prepare(
        'INSERT INTO minimarket_pos_sales
        (branch_id, invoice_no, customer_name, customer_phone, subtotal, grand_total, payment_method, payment_status, created_at)
        VALUES
        (:branch_id, :invoice_no, :customer_name, :customer_phone, :subtotal, :grand_total, :payment_method, :payment_status, NOW())'
    );

    $saleStmt->execute([
        ':branch_id' => $branchId,
        ':invoice_no' => $invoiceNo,
        ':customer_name' => $payload['customer_name'] ?? null,
        ':customer_phone' => $payload['customer_phone'] ?? null,
        ':subtotal' => $subtotal,
        ':grand_total' => $subtotal,
        ':payment_method' => $payload['payment_method'] ?? 'cash',
        ':payment_status' => 'paid',
    ]);

    $saleId = (int)$pdo->lastInsertId();

    foreach ($payload['items'] as $item) {
        $service->deductFifo(
            $branchId,
            (int)$item['menu_item_id'],
            (float)$item['qty']
        );

        $insertItem = $pdo->prepare(
            'INSERT INTO minimarket_pos_sale_items
            (sale_id, menu_item_id, item_name, sku, barcode, qty, unit_price, total_price, created_at)
            VALUES
            (:sale_id, :menu_item_id, :item_name, :sku, :barcode, :qty, :unit_price, :total_price, NOW())'
        );

        $insertItem->execute([
            ':sale_id' => $saleId,
            ':menu_item_id' => $item['menu_item_id'],
            ':item_name' => $item['item_name'],
            ':sku' => $item['sku'] ?? null,
            ':barcode' => $item['barcode'] ?? null,
            ':qty' => $item['qty'],
            ':unit_price' => $item['price'],
            ':total_price' => ((float)$item['qty'] * (float)$item['price']),
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'invoice_no' => $invoiceNo,
        'sale_id' => $saleId,
        'grand_total' => $subtotal,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
