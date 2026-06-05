<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\Order\OrderDTO;
use KopiBot\Domains\Order\OrderItemDTO;
use KopiBot\Domains\Order\OrderService;

$result = (new OrderService())->createOrder(new OrderDTO(
    tenantId: (int) ($_GET['tenant_id'] ?? 1),
    branchId: (int) ($_GET['branch_id'] ?? 1),
    customerId: (int) ($_GET['customer_id'] ?? 1),
    items: [
        new OrderItemDTO(1, 'Cappuccino', 2, 25000),
        new OrderItemDTO(2, 'Croissant', 1, 18000),
    ],
    channel: 'smoke_test'
));

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
