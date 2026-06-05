<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\CRM\CrmEventDTO;
use KopiBot\Domains\CRM\CrmService;

$service = new CrmService();

$result = $service->log(new CrmEventDTO(
    tenantId: (int) ($_GET['tenant_id'] ?? 1),
    branchId: (int) ($_GET['branch_id'] ?? 1),
    customerId: (int) ($_GET['customer_id'] ?? 1),
    eventType: (string) ($_GET['event_type'] ?? 'smoke.test'),
    payload: [
        'message' => 'CRM smoke test event',
        'time' => date('c'),
    ],
    source: 'smoke_test'
));

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
