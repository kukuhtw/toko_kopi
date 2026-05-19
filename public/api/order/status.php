<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\{Response, Sanitize};
use App\Models\OrderModel;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$orderNumber = Sanitize::get('order_number');
$orderId     = Sanitize::get('order_id', 'int');

$orderModel = new OrderModel();

if ($orderNumber) {
    $db   = \App\Config\Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM orders WHERE order_number = ? LIMIT 1');
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
} elseif ($orderId) {
    $order = $orderModel->getWithItems($orderId);
} else {
    Response::error('order_number or order_id required');
}

if (!$order) {
    Response::error('Order not found', 404);
}

Response::success($order);
