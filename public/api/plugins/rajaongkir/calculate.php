<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/app/Helpers/ApiBootstrap.php';
require_once dirname(__DIR__, 4) . '/plugins/rajaongkir-delivery/RajaOngkirDeliveryRepository.php';
require_once dirname(__DIR__, 4) . '/plugins/rajaongkir-delivery/RajaOngkirDeliveryService.php';

use App\Helpers\Response;
use App\Models\CartModel;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$body = json_decode((string)file_get_contents('php://input'), true) ?? [];
$branchId = (int)($body['branch_id'] ?? 0);
$sessionId = (string)($body['session_id'] ?? session_id());
$address = trim((string)($body['address'] ?? ''));
$postalCode = trim((string)($body['postal_code'] ?? ''));

if ($branchId <= 0) {
    Response::error('branch_id is required');
}
if ($address === '' && $postalCode === '') {
    Response::error('address or postal_code is required');
}

$service = new RajaOngkirDeliveryService();
if (!$service->isActive($branchId)) {
    Response::error('Plugin RajaOngkir tidak aktif untuk cabang ini.', 422);
}

$sessionKey = hash('sha256', "web:{$branchId}:{$sessionId}");
$cartModel = new CartModel();
$cart = $cartModel->getBySession($sessionKey);
if (!$cart) {
    Response::error('Cart not found', 404);
}

$items = $cartModel->getItems((int)$cart['id']);
if ($items === []) {
    Response::error('Cart is empty', 422);
}

try {
    $preview = $service->previewDelivery($branchId, $items, $address, $postalCode);
    Response::success($preview, 'Delivery fee calculated');
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 422);
}
