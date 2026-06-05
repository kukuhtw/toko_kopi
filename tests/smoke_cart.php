<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\Cart\CartItemDTO;
use KopiBot\Domains\Cart\CartService;

$service = new CartService();
$tenantId = (int) ($_GET['tenant_id'] ?? 1);
$branchId = (int) ($_GET['branch_id'] ?? 1);
$customerId = (int) ($_GET['customer_id'] ?? 1);
$sessionId = (string) ($_GET['session_id'] ?? 'demo-session');
$action = (string) ($_GET['action'] ?? 'add');

if ($action === 'checkout') {
    $result = $service->checkout($tenantId, $branchId, $customerId, $sessionId, 'Demo Customer', 'demo@example.com', '08123456789');
} else {
    $result = $service->addItem($tenantId, $branchId, $customerId, $sessionId, new CartItemDTO(
        productId: (int) ($_GET['product_id'] ?? 1),
        productName: (string) ($_GET['product_name'] ?? 'Cappuccino'),
        qty: (int) ($_GET['qty'] ?? 2),
        price: (float) ($_GET['price'] ?? 25000)
    ));
}

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
