<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\Response;
use App\Models\{CartModel, PromoModel, BranchModel};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$raw      = file_get_contents('php://input');
$body     = json_decode($raw, true) ?? [];
$branchId = (int)($body['branch_id'] ?? 0);
$rawSess  = $body['session_id'] ?? session_id();

if (!$branchId) {
    Response::error('branch_id is required');
}

$sessionKey = hash('sha256', "web:{$branchId}:{$rawSess}");
$cartModel  = new CartModel();
$cart       = $cartModel->getBySession($sessionKey);

if (!$cart) {
    Response::error('Cart not found', 404);
}

$items = $cartModel->getItems($cart['id']);
if (empty($items)) {
    Response::error('Cart is empty');
}

$subtotal   = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));
$tz         = (new BranchModel())->getTimezone($branchId);
$nowLocal   = (new \DateTime('now', new \DateTimeZone($tz)))->format('Y-m-d H:i:s');
$promoModel = new PromoModel();
$promo      = $promoModel->getBestAutoApply(
    $branchId,
    $subtotal,
    (int)($cart['customer_id'] ?? 0),
    $items,
    $nowLocal
);

if (!$promo) {
    Response::success([
        'promo_code' => null,
        'promo_title' => null,
        'discount_amount' => 0,
        'subtotal' => $subtotal,
        'total' => $subtotal,
    ], 'No auto promo available');
}

$discount = $promoModel->calculateDiscount($promo, $subtotal, $items);
$cartModel->applyPromo($cart['id'], (string)($promo['promo_code'] ?? ''), $discount);

Response::success([
    'promo_code'      => (string)($promo['promo_code'] ?? ''),
    'promo_title'     => (string)($promo['title'] ?? ''),
    'discount_amount' => $discount,
    'subtotal'        => $subtotal,
    'total'           => max(0, $subtotal - $discount),
], 'Auto promo applied');
