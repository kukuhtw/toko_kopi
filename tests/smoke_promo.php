<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\Promo\PromoDTO;
use KopiBot\Domains\Promo\PromoService;

$result = (new PromoService())->apply(new PromoDTO(
    tenantId: (int) ($_GET['tenant_id'] ?? 1),
    branchId: (int) ($_GET['branch_id'] ?? 1),
    promoCode: (string) ($_GET['promo_code'] ?? 'HEMAT10'),
    subtotal: (float) ($_GET['subtotal'] ?? 100000),
    customerId: isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : null,
    orderId: isset($_GET['order_id']) ? (int) $_GET['order_id'] : null
));

header('Content-Type: application/json');
echo json_encode($result->toArray(), JSON_PRETTY_PRINT) . PHP_EOL;
