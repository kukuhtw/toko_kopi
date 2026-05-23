<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\Response;
use App\Models\BranchModel;
use App\Models\CartModel;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$branchId = (int)($_GET['branch_id'] ?? 0);
$sessionId = (string)($_GET['session_id'] ?? session_id());

if ($branchId <= 0) {
    Response::error('branch_id is required');
}

$sessionKey = hash('sha256', "web:{$branchId}:{$sessionId}");
$cartModel = new CartModel();
$branchModel = new BranchModel();
$cart = $cartModel->getBySession($sessionKey);

if (!$cart) {
    Response::success([
        'items' => [],
        'total' => 0,
        'summary' => [
            'item_count' => 0,
            'line_count' => 0,
            'subtotal' => 0,
            'discount_amount' => 0,
            'promo_code' => null,
            'loyalty_discount_amount' => 0,
            'ppn_rate' => $branchModel->getPpnRate($branchId),
            'ppn_amount' => 0,
            'total' => 0,
        ],
        'cart' => null,
    ], 'Cart empty');
}

$items = $cartModel->getItems((int)$cart['id']);
$itemCount = 0;
foreach ($items as $item) {
    $itemCount += (int)($item['quantity'] ?? 0);
}
$subtotal = $cartModel->getTotal((int)$cart['id']);
$discountAmount = (float)($cart['discount_amount'] ?? 0);
$loyaltyDiscount = (float)($cart['loyalty_discount_amount'] ?? 0);
$ppnRate = $branchModel->getPpnRate($branchId);
$afterDiscount = max(0.0, $subtotal - $discountAmount);
$ppnAmount = $ppnRate > 0 ? round($afterDiscount * $ppnRate / 100, 2) : 0.0;
$total = $afterDiscount + $ppnAmount;

Response::success([
    'cart' => $cart,
    'items' => $items,
    'total' => $total,
    'summary' => [
        'item_count' => $itemCount,
        'line_count' => count($items),
        'subtotal' => $subtotal,
        'discount_amount' => $discountAmount,
        'promo_code' => (string)($cart['promo_code'] ?? ''),
        'loyalty_discount_amount' => $loyaltyDiscount,
        'ppn_rate' => $ppnRate,
        'ppn_amount' => $ppnAmount,
        'total' => $total,
    ],
], 'OK');
