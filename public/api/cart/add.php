<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\{Response, Sanitize};
use App\Models\{CartModel, MenuModel, CustomerModel};
use App\Plugin\HookManager;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

$branchId   = (int) ($body['branch_id']    ?? 0);
$menuItemId = (int) ($body['menu_item_id'] ?? 0);
$variantId  = isset($body['variant_id']) ? (int)$body['variant_id'] : null;
$qty        = max(1, (int) ($body['quantity'] ?? 1));
$notes      = Sanitize::string($body['notes'] ?? '');
$sessionId  = $body['session_id'] ?? session_id();

if (!$branchId || !$menuItemId) Response::error('branch_id and menu_item_id required');

$menuModel = new MenuModel();
$item      = $menuModel->getItemForBranch($menuItemId, $branchId);

if (!$item || !(bool)$item['effective_available']) Response::error('Item not available', 404);

$variant = null;
$unitPrice = (float)$item['effective_price'];
if ($variantId !== null && $variantId > 0) {
    foreach (($item['variants'] ?? []) as $row) {
        if ((int)($row['id'] ?? 0) === $variantId) {
            $variant = $row;
            $unitPrice = (float)($row['effective_price'] ?? $unitPrice);
            break;
        }
    }
    if ($variant === null) Response::error('Variant not available', 404);
}

// Resolve customer
$customerModel = new CustomerModel();
$customer      = $customerModel->findOrCreate('web', $sessionId);

$cartModel  = new CartModel();
$sessionKey = hash('sha256', "web:{$branchId}:{$sessionId}");
$cart       = $cartModel->getOrCreate($sessionKey, $branchId, $customer['id']);

$cartModel->addItem(
    $cart['id'],
    $menuItemId,
    $qty,
    $unitPrice,
    $notes,
    $variantId > 0 ? $variantId : null,
    $variant['label'] ?? null
);

// Action: item ditambahkan ke keranjang
HookManager::doAction('cart.item_added', [
    'menu_item_id' => $menuItemId,
    'quantity'     => $qty,
    'unit_price'   => $unitPrice,
    'variant_id'   => $variantId > 0 ? $variantId : null,
    'notes'        => $notes,
], $cart, $branchId);

$updatedItems = $cartModel->getItems($cart['id']);
$total        = $cartModel->getTotal($cart['id']);

Response::success(['items' => $updatedItems, 'total' => $total]);
