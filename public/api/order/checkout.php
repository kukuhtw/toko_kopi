<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\{Response, Sanitize};
use App\Models\{CartModel, OrderModel, CustomerModel, PromoModel, BranchModel};
use App\Plugin\HookManager;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

// Build the same session key that cart/add.php uses
$rawSession = $body['session_id'] ?? $body['session_key'] ?? session_id();
$branchId   = (int)($body['branch_id'] ?? 0);
$sessionKey = $branchId
    ? hash('sha256', "web:{$branchId}:{$rawSession}")
    : $rawSession;

$cartModel = new CartModel();
$cart      = $cartModel->getBySession($sessionKey);

if (!$cart) {
    Response::error('Cart not found');
}

$items = $cartModel->getItems($cart['id']);
if (empty($items)) {
    Response::error('Cart is empty');
}

// Apply promo if sent and not yet applied on cart
$promoCode = strtoupper(trim($body['promo_code'] ?? ''));
$subtotal  = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));
$tz        = (new BranchModel())->getTimezone($branchId);
$nowLocal  = (new \DateTime('now', new \DateTimeZone($tz)))->format('Y-m-d H:i:s');
$promoModel = new PromoModel();

if ($promoCode !== '' && ($cart['promo_code'] ?? '') !== $promoCode) {
    $promo      = $promoModel->findByCode($promoCode, $branchId, $nowLocal);
    if ($promo && $subtotal >= (float)$promo['min_order']) {
        $discount = $promoModel->calculateDiscount($promo, $subtotal, $items);
        $cartModel->applyPromo($cart['id'], $promoCode, $discount);
        $cart = $cartModel->getBySession($sessionKey);
    }
} elseif (empty($cart['promo_code'])) {
    $autoPromo = $promoModel->getBestAutoApply(
        $branchId,
        $subtotal,
        (int)($cart['customer_id'] ?? 0),
        $items,
        $nowLocal
    );
    if ($autoPromo) {
        $discount = $promoModel->calculateDiscount($autoPromo, $subtotal, $items);
        $cartModel->applyPromo($cart['id'], (string)($autoPromo['promo_code'] ?? ''), $discount);
        $cart = $cartModel->getBySession($sessionKey);
    }
}

$required = ['name', 'address'];
foreach ($required as $field) {
    if (empty($body[$field])) {
        Response::error("Field '{$field}' is required");
    }
}

$customerModel = new CustomerModel();
$resolvedCustomer = $customerModel->resolveWebCustomer(
    (string)$rawSession,
    Sanitize::string((string)($body['name'] ?? '')),
    (string)($body['email'] ?? ''),
    (string)($body['whatsapp'] ?? '')
);
$customerId    = (int)($resolvedCustomer['id'] ?? $cart['customer_id'] ?? 0);
$cartModel->getOrCreate($sessionKey, $branchId, $customerId);
$cart = $cartModel->getBySession($sessionKey);
$customer      = $customerModel->find($customerId);

if (!$customer) {
    Response::error('Customer not found', 404);
}

$customerData = [
    'name'        => Sanitize::string($body['name']),
    'email'       => filter_var($body['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null,
    'whatsapp'    => preg_replace('/[^0-9+]/', '', $body['whatsapp'] ?? ''),
    'address'     => Sanitize::string($body['address']),
    'postal_code' => preg_replace('/\D/', '', $body['postal_code'] ?? ''),
];

// Filter: plugin bisa modifikasi atau validasi data checkout sebelum order dibuat
$customerData = HookManager::applyFilters('cart.before_checkout', $customerData, $cart, $items, $branchId);

try {
    $cartWithChannel = array_merge($cart, ['channel' => 'web']);
    $orderModel      = new OrderModel();
    $ppnRate         = (new BranchModel())->getPpnRate($branchId);
    $orderId         = $orderModel->createFromCart($cartWithChannel, $items, $customerData, $customerId, $ppnRate);
    $order           = $orderModel->getWithItems($orderId);
    $orderResponse   = HookManager::applyFilters('order.checkout_response', $order, $order, $branchId);

    $cartModel->clearCart($cart['id']);

    // Update customer profile
    $customerModel->updateInfo($customerId, ['name' => $customerData['name'], 'email' => $customerData['email']]);
    $customerModel->updateProfile($customerId, [
        'address'     => $customerData['address'],
        'postal_code' => $customerData['postal_code'],
    ]);

    Response::success($orderResponse, 'Order created successfully');
} catch (\Throwable $e) {
    if ($e instanceof \RuntimeException) {
        Response::error($e->getMessage(), 422);
    }
    error_log('[checkout] ' . $e->getMessage());
    Response::error('Failed to create order', 500);
}
