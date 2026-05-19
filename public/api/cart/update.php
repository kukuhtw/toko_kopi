<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\Response;
use App\Models\{CartModel, CustomerModel};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

$branchId   = (int) ($body['branch_id']    ?? 0);
$menuItemId = (int) ($body['menu_item_id'] ?? 0);
$variantId  = isset($body['variant_id']) ? (int)$body['variant_id'] : null;
$qty        = (int) ($body['quantity']     ?? 0);
$sessionId  = $body['session_id'] ?? session_id();

if (!$branchId || !$menuItemId) Response::error('branch_id and menu_item_id required');

$customerModel = new CustomerModel();
$customer      = $customerModel->findOrCreate('web', $sessionId);
$cartModel     = new CartModel();
$sessionKey    = hash('sha256', "web:{$branchId}:{$sessionId}");
$cart          = $cartModel->getBySession($sessionKey);

if (!$cart) Response::error('Cart not found', 404);

$cartModel->updateItem($cart['id'], $menuItemId, $qty, $variantId > 0 ? $variantId : null);

$updatedItems = $cartModel->getItems($cart['id']);
$total        = $cartModel->getTotal($cart['id']);

Response::success(['items' => $updatedItems, 'total' => $total]);
