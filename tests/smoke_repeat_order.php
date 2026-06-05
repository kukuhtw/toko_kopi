<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\Customer\RepeatOrderService;

$result = (new RepeatOrderService())->repeatLastOrder(
    tenantId: (int) ($_GET['tenant_id'] ?? 1),
    branchId: (int) ($_GET['branch_id'] ?? 1),
    customerId: (int) ($_GET['customer_id'] ?? 1),
    sessionId: (string) ($_GET['session_id'] ?? 'web:guest')
);

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
