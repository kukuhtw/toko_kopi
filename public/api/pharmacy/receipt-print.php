<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Services\PharmacyEscposPrinterService;

header('Content-Type: text/plain');

$sale = [
    'invoice_no' => $_GET['invoice_no'] ?? 'POS-DEMO',
    'grand_total' => 120000,
];

$items = [
    [
        'item_name' => 'Paracetamol 500mg',
        'qty' => 2,
        'total_price' => 24000,
    ],
    [
        'item_name' => 'Vitamin C 1000mg',
        'qty' => 1,
        'total_price' => 35000,
    ],
];

$service = new PharmacyEscposPrinterService();

echo $service->generateReceipt($sale, $items);
