<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\{Currency, Response};
use App\Models\{CartModel, CustomerModel, BranchModel};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$raw      = file_get_contents('php://input');
$body     = json_decode($raw, true) ?? [];
$branchId = (int)($body['branch_id'] ?? 0);
$rawSess  = $body['session_id'] ?? session_id();
$action   = strtolower(trim((string)($body['action'] ?? 'apply')));
$points   = max(0, (int)($body['points'] ?? 0));
$customerName = trim((string)($body['customer_name'] ?? ''));
$customerEmail = trim((string)($body['customer_email'] ?? ''));
$customerWhatsapp = preg_replace('/[^0-9+]/', '', (string)($body['customer_whatsapp'] ?? ''));

if (!$branchId) {
    Response::error('branch_id is required');
}

$customerModel = new CustomerModel();
$customer      = $customerModel->resolveWebCustomer((string)$rawSess, $customerName, $customerEmail, $customerWhatsapp);
$sessionKey    = hash('sha256', "web:{$branchId}:{$rawSess}");
$cartModel     = new CartModel();
$cart          = $cartModel->getOrCreate($sessionKey, $branchId, (int)$customer['id']);
$repo          = new LoyaltyPointRepository();

if ($action === 'clear') {
    $repo->clearRedemptionFromCart((int)$cart['id']);
    $updatedCart = $cartModel->getBySession($sessionKey) ?: $cart;
    Response::success([
        'cart_loyalty_points'   => (int)($updatedCart['loyalty_points_redeemed'] ?? 0),
        'cart_loyalty_discount' => (float)($updatedCart['loyalty_discount_amount'] ?? 0),
    ], 'Redeem poin dibatalkan.');
}

$items = $cartModel->getItems((int)$cart['id']);
if (empty($items)) {
    Response::error('Cart is empty', 422);
}

$balance  = $repo->getBalance($branchId, (int)$customer['id']);
$settings = LoyaltyPointPlugin::getRedeemSettings($branchId);
$availablePoints = (int)($balance['balance_points'] ?? 0);

if ($availablePoints <= 0) {
    Response::error('Belum ada poin yang bisa dipakai.', 422);
}

if ($points <= 0) {
    $points = $settings['points_unit'];
}

$points = min($points, $availablePoints);
if ($points < $settings['min_points']) {
    Response::error('Minimal redeem ' . $settings['min_points'] . ' poin.', 422);
}

$subtotal        = array_sum(array_map(static fn(array $i): float => (float)$i['quantity'] * (float)$i['unit_price'], $items));
$currentLoyalty  = (float)($cart['loyalty_discount_amount'] ?? 0);
$promoDiscount   = max(0.0, (float)($cart['discount_amount'] ?? 0) - $currentLoyalty);
$maxDiscountBase = max(0.0, $subtotal - $promoDiscount);
$discount        = LoyaltyPointPlugin::calculateRedeemDiscount($points, $settings['points_unit'], $settings['value_amount']);
$discount        = min($discount, $maxDiscountBase);

if ($discount <= 0) {
    Response::error('Total cart tidak memenuhi syarat redeem poin.', 422);
}

$actualPoints = LoyaltyPointPlugin::calculateRedeemPointsForDiscount($discount, $settings['points_unit'], $settings['value_amount']);
$repo->applyRedemptionToCart((int)$cart['id'], $actualPoints, $discount);
$updatedCart = $cartModel->getBySession($sessionKey) ?: $cart;
$currency = (new BranchModel())->getCurrency($branchId);

Response::success([
    'cart_loyalty_points'   => (int)($updatedCart['loyalty_points_redeemed'] ?? 0),
    'cart_loyalty_discount' => (float)($updatedCart['loyalty_discount_amount'] ?? 0),
    'discount_label'        => Currency::format($discount, $currency),
    'balance_points'        => $availablePoints,
], 'Redeem poin berhasil diterapkan.');
