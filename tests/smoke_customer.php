<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\Customer\CustomerDTO;
use KopiBot\Domains\Customer\CustomerService;

$result = (new CustomerService())->findOrCreate(new CustomerDTO(
    tenantId: (int) ($_GET['tenant_id'] ?? 1),
    branchId: (int) ($_GET['branch_id'] ?? 1),
    name: (string) ($_GET['name'] ?? 'Demo Customer'),
    email: (string) ($_GET['email'] ?? 'demo@example.com'),
    phone: (string) ($_GET['phone'] ?? '08123456789'),
    whatsapp: (string) ($_GET['whatsapp'] ?? '08123456789'),
    address: (string) ($_GET['address'] ?? 'Jakarta'),
    source: 'smoke_test'
));

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
