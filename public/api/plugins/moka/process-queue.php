<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/app/Config/config.php';
require_once dirname(__DIR__, 4) . '/plugins/moka-connect-private-solution/MokaConnectRepository.php';
require_once dirname(__DIR__, 4) . '/plugins/moka-connect-private-solution/MokaConnectClient.php';
require_once dirname(__DIR__, 4) . '/plugins/moka-connect-private-solution/MokaConnectService.php';

use App\Helpers\ApiBootstrap;
use App\Helpers\Response;

ApiBootstrap::init();

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    Response::error('Method not allowed', 405);
}

$repo = new MokaConnectRepository();
$service = new MokaConnectService($repo);
$providedToken = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$expectedToken = $repo->getGlobalSetting('runner_token');

if ($expectedToken === '') {
    Response::error('runner token is not configured', 403);
}

if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    Response::error('invalid runner token', 401);
}

$branchId = (int)($_GET['branch'] ?? $_POST['branch'] ?? 0);
$limit = max(1, min(100, (int)($_GET['limit'] ?? $_POST['limit'] ?? 20)));

$result = $branchId > 0
    ? $service->processPendingQueue($branchId, $limit)
    : $service->processAllActiveBranches($limit);

Response::success([
    'branch_id' => $branchId > 0 ? $branchId : null,
    'processed' => (int)($result['processed'] ?? 0),
    'success_count' => (int)($result['success_count'] ?? 0),
    'failed_count' => (int)($result['failed_count'] ?? 0),
], $result['message'] ?? 'Runner selesai');
