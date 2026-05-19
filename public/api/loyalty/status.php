<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\Response;
use App\Models\{CartModel, CustomerModel};

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

$customerModel = new CustomerModel();
$customer      = $customerModel->findOrCreate('web', (string)$rawSess);
$sessionKey    = hash('sha256', "web:{$branchId}:{$rawSess}");
$cartModel     = new CartModel();
$cart          = $cartModel->getOrCreate($sessionKey, $branchId, (int)$customer['id']);

$repo     = new LoyaltyPointRepository();
$balance  = $repo->getBalance($branchId, (int)$customer['id']);
$settings = LoyaltyPointPlugin::getRedeemSettings($branchId);

Response::success([
    'balance_points'            => (int)($balance['balance_points'] ?? 0),
    'lifetime_points'           => (int)($balance['lifetime_points'] ?? 0),
    'cart_loyalty_points'       => (int)($cart['loyalty_points_redeemed'] ?? 0),
    'cart_loyalty_discount'     => (float)($cart['loyalty_discount_amount'] ?? 0),
    'redeem_points_unit'        => (int)$settings['points_unit'],
    'redeem_value_amount'       => (float)$settings['value_amount'],
    'min_redeem_points'         => (int)$settings['min_points'],
], 'Loyalty status loaded');
