<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/app/Config/config.php';
require_once dirname(__DIR__, 4) . '/plugins/woocommerce-connector/WooCommerceConnectorRepository.php';
require_once dirname(__DIR__, 4) . '/plugins/woocommerce-connector/WooCommerceConnectorClient.php';
require_once dirname(__DIR__, 4) . '/plugins/woocommerce-connector/WooCommerceConnectorService.php';

use App\Helpers\ApiBootstrap;
use App\Helpers\Response;

ApiBootstrap::init();

$branchId = (int)($_GET['branch'] ?? 0);
$repo = new WooCommerceConnectorRepository();
$service = new WooCommerceConnectorService($repo);
$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);
$providedSignature = trim((string)($_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE'] ?? ''));
$providedSecret = trim((string)($_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? ''));
$expectedSecret = $branchId > 0 ? $repo->getBranchSetting($branchId, 'webhook_secret') : '';

if ($branchId <= 0) {
    Response::error('branch is required', 400);
}

if ($expectedSecret !== '') {
    $signatureVerified = false;
    $fallbackVerified = false;

    if ($providedSignature !== '') {
        $computedSignature = base64_encode(hash_hmac('sha256', $rawBody, $expectedSecret, true));
        $signatureVerified = hash_equals($computedSignature, $providedSignature);
    }

    if (!$signatureVerified && $providedSecret !== '') {
        $fallbackVerified = hash_equals($expectedSecret, $providedSecret);
    }

    if (!$signatureVerified && !$fallbackVerified) {
        $repo->logSync($branchId, 'webhook', 'webhook.received', 'failed', 'branch:' . $branchId, [
            'headers' => [
                'x_wc_webhook_signature' => $providedSignature !== '' ? '[provided]' : '[missing]',
                'x_webhook_secret' => $providedSecret !== '' ? '[provided]' : '[missing]',
            ],
            'body' => $payload ?? $rawBody,
        ], [
            'message' => $providedSignature !== ''
                ? 'WooCommerce webhook signature mismatch.'
                : 'Webhook secret/signature missing or invalid.',
        ], 'inbound');
        Response::error('invalid webhook signature', 401);
    }
}

$repo->logSync($branchId, 'webhook', 'webhook.received', 'success', 'branch:' . $branchId, [
    'headers' => [
        'x_wc_webhook_signature' => $providedSignature !== '' ? '[provided]' : '[missing]',
        'x_webhook_secret' => $providedSecret !== '' ? '[provided]' : '[missing]',
    ],
    'body' => $payload ?? $rawBody,
], ['message' => 'Inbound WooCommerce webhook accepted.'], 'inbound');

if (is_array($payload)) {
    $sync = $service->handleInboundWebhook($branchId, $payload);
    Response::success([
        'branch_id' => $branchId,
        'accepted' => true,
        'mode' => 'inbound_sync',
        'sync' => $sync,
    ], $sync['message'] ?? 'Webhook WooCommerce diterima');
}

Response::success([
    'branch_id' => $branchId,
    'accepted' => true,
    'mode' => 'raw_only',
], 'Webhook WooCommerce diterima');
