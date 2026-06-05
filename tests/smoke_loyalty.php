<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\Loyalty\LoyaltyService;

$result = (new LoyaltyService())->earnFromOrder(
    tenantId: (int) ($_GET['tenant_id'] ?? 1),
    branchId: (int) ($_GET['branch_id'] ?? 1),
    customerId: (int) ($_GET['customer_id'] ?? 1),
    orderId: (int) ($_GET['order_id'] ?? 1),
    grandTotal: (float) ($_GET['grand_total'] ?? 68000)
);

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
