<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\Response;
use App\Models\CartModel;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$raw       = file_get_contents('php://input');
$body      = json_decode($raw, true) ?? [];
$branchId  = (int) ($body['branch_id'] ?? 0);
$sessionId = $body['session_id'] ?? session_id();

if (!$branchId) Response::error('branch_id required');

$cartModel  = new CartModel();
$sessionKey = hash('sha256', "web:{$branchId}:{$sessionId}");
$cart       = $cartModel->getBySession($sessionKey);

if (!$cart) Response::error('Cart not found', 404);

$cartModel->clearCart($cart['id']);
Response::success(null, 'Cart cleared');
