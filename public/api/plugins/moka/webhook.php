<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/app/Config/config.php';
require_once dirname(__DIR__, 4) . '/plugins/moka-connect-private-solution/MokaConnectRepository.php';
require_once dirname(__DIR__, 4) . '/plugins/moka-connect-private-solution/MokaConnectClient.php';
require_once dirname(__DIR__, 4) . '/plugins/moka-connect-private-solution/MokaConnectService.php';

use App\Helpers\ApiBootstrap;
use App\Helpers\Response;

ApiBootstrap::init();

$branchId = (int)($_GET['branch'] ?? 0);
$repo = new MokaConnectRepository();
$service = new MokaConnectService($repo);
$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);
$providedSecret = (string)($_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? $_SERVER['HTTP_X_MOKA_SIGNATURE'] ?? '');
$expectedSecret = $branchId > 0 ? $repo->getBranchSetting($branchId, 'webhook_secret') : '';

if ($branchId <= 0) {
    Response::error('branch is required', 400);
}

if ($expectedSecret !== '' && $providedSecret !== $expectedSecret) {
    $repo->logSync($branchId, 'webhook', 'webhook.received', 'failed', 'branch:' . $branchId, [
        'headers' => [
            'x_webhook_secret' => $providedSecret !== '' ? '[provided]' : '[missing]',
        ],
        'body' => $payload ?? $rawBody,
    ], ['message' => 'Webhook secret mismatch.'], 'inbound');
    Response::error('invalid webhook secret', 401);
}

$repo->logSync($branchId, 'webhook', 'webhook.received', 'success', 'branch:' . $branchId, [
    'body' => $payload ?? $rawBody,
], ['message' => 'Inbound Moka webhook accepted by scaffold.'], 'inbound');

if (is_array($payload)) {
    $sync = $service->handleInboundWebhook($branchId, $payload);
    Response::success([
        'branch_id' => $branchId,
        'accepted' => true,
        'mode' => 'inbound_sync',
        'sync' => $sync,
    ], $sync['message'] ?? 'Webhook Moka diterima');
}

Response::success([
    'branch_id' => $branchId,
    'accepted' => true,
    'mode' => 'raw_only',
], 'Webhook Moka diterima');
