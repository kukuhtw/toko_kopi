<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\FAQ\FaqService;

$result = (new FaqService())->answer(
    tenantId: (int) ($_GET['tenant_id'] ?? 1),
    branchId: isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 1,
    question: (string) ($_GET['question'] ?? 'jam buka'),
    customerId: isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : null
);

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
