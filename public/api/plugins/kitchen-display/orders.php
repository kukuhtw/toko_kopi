<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\{Auth, Response};
use App\Plugin\PluginLoader;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

if (!PluginLoader::isLoaded('kitchen-display')) {
    Response::error('Plugin Kitchen Display tidak aktif', 403);
}

$user     = Auth::user();
$branchId = (int)($user['branch_id'] ?? 0);

if (!$branchId && Auth::isSuperAdmin()) {
    $branchId = (int)($_GET['branch_id'] ?? 0);
}

if (!$branchId) {
    Response::error('Branch tidak valid', 400);
}

require_once BASE_PATH . '/plugins/kitchen-display/KitchenDisplayRepository.php';

$repo       = new KitchenDisplayRepository();
$active     = $repo->getActiveOrders($branchId);
$completed  = $repo->getRecentCompleted($branchId, 25);

$pending    = array_values(array_filter($active, fn($o) => $o['order_status'] === 'pending'));
$processing = array_values(array_filter($active, fn($o) => $o['order_status'] === 'processing'));

Response::success([
    'pending'    => $pending,
    'processing' => $processing,
    'completed'  => $completed,
    'ts'         => time(),
]);
