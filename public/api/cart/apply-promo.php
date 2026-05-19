<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\{Response, Sanitize, Currency};
use App\Models\{CartModel, PromoModel, BranchModel};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$raw      = file_get_contents('php://input');
$body     = json_decode($raw, true) ?? [];
$branchId = (int)($body['branch_id'] ?? 0);
$rawSess  = $body['session_id'] ?? session_id();
$code     = strtoupper(trim(Sanitize::string($body['promo_code'] ?? '')));

if (!$branchId || $code === '') {
    Response::error('branch_id and promo_code are required');
}

$sessionKey = hash('sha256', "web:{$branchId}:{$rawSess}");
$cartModel  = new CartModel();
$cart       = $cartModel->getBySession($sessionKey);

if (!$cart) {
    Response::error('Cart not found');
}

$items = $cartModel->getItems($cart['id']);
if (empty($items)) {
    Response::error('Cart is empty');
}

$subtotal   = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));
$branchModel = new BranchModel();
$currency   = $branchModel->getCurrency($branchId);
$tz         = $branchModel->getTimezone($branchId);
$nowLocal   = (new \DateTime('now', new \DateTimeZone($tz)))->format('Y-m-d H:i:s');
$promoModel = new PromoModel();
$promo      = $promoModel->findByCode($code, $branchId, $nowLocal);

if (!$promo) {
    Response::error('Kode promo tidak valid atau sudah tidak aktif.', 422);
}

if ($subtotal < (float)$promo['min_order']) {
    $min = Currency::format((float)$promo['min_order'], $currency);
    Response::error("Minimum order {$min} untuk menggunakan promo ini.", 422);
}

$discount = $promoModel->calculateDiscount($promo, $subtotal, $items);
$cartModel->applyPromo($cart['id'], $code, $discount);

Response::success([
    'promo_code'      => $code,
    'promo_title'     => $promo['title'],
    'discount_amount' => $discount,
    'subtotal'        => $subtotal,
    'total'           => max(0, $subtotal - $discount),
], 'Promo berhasil diterapkan!');
