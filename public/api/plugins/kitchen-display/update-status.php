<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\{Auth, Response, Csrf};
use App\Models\OrderModel;
use App\Plugin\PluginLoader;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

if (!PluginLoader::isLoaded('kitchen-display')) {
    Response::error('Plugin Kitchen Display tidak aktif', 403);
}

// Validasi CSRF tanpa rotate agar token tetap valid untuk banyak AJAX call
if (!Csrf::isValidRequest(false)) {
    Response::error('Invalid CSRF token', 403);
}

$body    = (string) file_get_contents('php://input');
$payload = json_decode($body, true);

$orderId   = (int)($payload['order_id']  ?? 0);
$newStatus = (string)($payload['status'] ?? '');

$allowed = ['processing', 'completed', 'cancelled'];
if (!$orderId || !in_array($newStatus, $allowed, true)) {
    Response::error('Parameter tidak valid', 400);
}

$user       = Auth::user();
$orderModel = new OrderModel();
$order      = $orderModel->find($orderId);

if (!$order) {
    Response::error('Order tidak ditemukan', 404);
}

if (!Auth::canAccessBranch((int)$order['branch_id'])) {
    Response::error('Akses ditolak', 403);
}

$orderModel->updateStatus($orderId, $newStatus, (int)$user['id']);

Response::success(['order_id' => $orderId, 'status' => $newStatus], 'Status berhasil diperbarui');
