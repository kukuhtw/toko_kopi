<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;
use App\Services\PharmacyInventoryService;

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!$payload || empty($payload['items'])) {
        throw new RuntimeException('Invalid payload.');
    }

    $pdo = Database::getInstance();
    $service = new PharmacyInventoryService($pdo);

    $branchId = (int)($payload['branch_id'] ?? 1);
    $invoiceNo = 'POS-' . date('YmdHis');

    $prescriptionRef = trim((string)($payload['prescription_reference'] ?? ''));
    $pharmacistApproved = (bool)($payload['pharmacist_approved'] ?? false);

    $checkPrescription = $pdo->prepare(
        'SELECT pm.requires_prescription, mi.name
         FROM menu_items mi
         LEFT JOIN pharmacy_product_metadata pm ON pm.menu_item_id = mi.id
         WHERE mi.id = :menu_item_id
         LIMIT 1'
    );

    $subtotal = 0;

    foreach ($payload['items'] as $item) {
        $subtotal += ((float)$item['qty'] * (float)$item['price']);

        $checkPrescription->execute([
            ':menu_item_id' => (int)$item['menu_item_id'],
        ]);

        $product = $checkPrescription->fetch(PDO::FETCH_ASSOC);

        if ($product && (int)($product['requires_prescription'] ?? 0) === 1) {
            if ($prescriptionRef === '' && !$pharmacistApproved) {
                throw new RuntimeException(
                    'Prescription required for product: ' . ($product['name'] ?? 'Unknown Product')
                );
            }
        }
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO pharmacy_pos_sales
         (branch_id, invoice_no, customer_name, customer_phone, subtotal, grand_total, payment_method, payment_status)
         VALUES (:branch_id, :invoice_no, :customer_name, :customer_phone, :subtotal, :grand_total, :payment_method, :payment_status)'
    );

    $stmt->execute([
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
            (float)$item['qty'],
            'pos_sale',
            $saleId,
            null
        );

        $insert = $pdo->prepare(
            'INSERT INTO pharmacy_pos_sale_items
             (sale_id, menu_item_id, item_name, qty, unit_price, total_price)
             VALUES (:sale_id, :menu_item_id, :item_name, :qty, :unit_price, :total_price)'
        );

        $insert->execute([
            ':sale_id' => $saleId,
            ':menu_item_id' => (int)$item['menu_item_id'],
            ':item_name' => (string)$item['item_name'],
            ':qty' => (float)$item['qty'],
            ':unit_price' => (float)$item['price'],
            ':total_price' => ((float)$item['qty'] * (float)$item['price']),
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'invoice_no' => $invoiceNo,
        'sale_id' => $saleId,
        'grand_total' => $subtotal,
        'prescription_verified' => ($prescriptionRef !== '' || $pharmacistApproved),
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
