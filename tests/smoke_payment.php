<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\Payment\PaymentDTO;
use KopiBot\Domains\Payment\PaymentService;

$result = (new PaymentService())->createCheckout(new PaymentDTO(
    tenantId: (int) ($_GET['tenant_id'] ?? 1),
    branchId: (int) ($_GET['branch_id'] ?? 1),
    orderId: (int) ($_GET['order_id'] ?? 1),
    orderNo: (string) ($_GET['order_no'] ?? 'ORD-DEMO'),
    amount: (float) ($_GET['amount'] ?? 68000),
    customerName: (string) ($_GET['customer_name'] ?? 'Demo Customer'),
    customerEmail: (string) ($_GET['customer_email'] ?? 'demo@example.com'),
    customerPhone: (string) ($_GET['customer_phone'] ?? '08123456789'),
    gateway: 'mock'
));

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
