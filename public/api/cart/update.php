<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\Response;
use App\Models\{CartModel, CustomerModel};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

$branchId   = (int) ($body['branch_id']    ?? 0);
$cartItemId = (int) ($body['cart_item_id'] ?? 0);
$menuItemId = (int) ($body['menu_item_id'] ?? 0);
$variantId  = isset($body['variant_id']) ? (int)$body['variant_id'] : null;
$qty        = (int) ($body['quantity']     ?? 0);
$sessionId  = $body['session_id'] ?? session_id();
$customerName = trim((string)($body['customer_name'] ?? ''));
$customerEmail = trim((string)($body['customer_email'] ?? ''));
$customerWhatsapp = preg_replace('/[^0-9+]/', '', (string)($body['customer_whatsapp'] ?? ''));

if (!$branchId || (!$cartItemId && !$menuItemId)) Response::error('branch_id and cart_item_id or menu_item_id required');

$customerModel = new CustomerModel();
$customer      = $customerModel->resolveWebCustomer((string)$sessionId, $customerName, $customerEmail, $customerWhatsapp);
$cartModel     = new CartModel();
$sessionKey    = hash('sha256', "web:{$branchId}:{$sessionId}");
$cart          = $cartModel->getOrCreate($sessionKey, $branchId, (int)$customer['id']);

$updated = $cartItemId > 0
    ? $cartModel->updateItemById($cartItemId, $qty)
    : $cartModel->updateItem($cart['id'], $menuItemId, $qty, $variantId > 0 ? $variantId : null);

$updatedItems = $cartModel->getItems($cart['id']);
$total        = $cartModel->getTotal($cart['id']);

Response::success(['items' => $updatedItems, 'total' => $total, 'updated' => $updated]);
