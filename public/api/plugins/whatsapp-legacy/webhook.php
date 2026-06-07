<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/runtime.php';
require_once BASE_PATH . '/app/WhatsAppProviders/WhatsAppProviderInterface.php';
require_once BASE_PATH . '/app/WhatsAppProviders/ProviderFactory.php';
require_once BASE_PATH . '/app/WhatsAppProviders/WablasProvider.php';
require_once BASE_PATH . '/app/WhatsAppProviders/GenericWebhookProvider.php';
require_once BASE_PATH . '/app/Services/CustomerConversationService.php';
require_once BASE_PATH . '/app/Services/WhatsAppSharedInboxService.php';

use App\Plugin\HookManager;
use App\Services\CustomerConversationService;
use App\Services\WhatsAppSharedInboxService;
use App\WhatsAppProviders\ProviderFactory;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true) ?? [];
$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if ($payload === [] && !empty($_POST)) {
    $payload = $_POST;
} elseif ($payload === [] && str_contains($contentType, 'application/x-www-form-urlencoded')) {
    parse_str($rawBody, $payload);
}
$headers = getallheaders() ?: [];

$branchSetting = null;
$adapterClass = null;

if (!empty($payload['data']['phone'])) {
    $adapterClass = 'WablasProvider';
    $waNumber = $payload['device'] ?? $payload['data']['device'] ?? '';
    if ($waNumber) {
        $branchSetting = ProviderFactory::findByWaNumber($waNumber);
    }
} elseif (!empty($payload['from'])) {
    $adapterClass = 'GenericWebhookProvider';
}

if (!$branchSetting) {
    $waNumber = $payload['device'] ?? $payload['to'] ?? $payload['data']['device'] ?? '';
    if ($waNumber) {
        $branchSetting = ProviderFactory::findByWaNumber($waNumber);
    }
}

if (!$branchSetting || !$adapterClass) {
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'branch_not_found']);
    exit;
}

$provider = ProviderFactory::make($adapterClass, $branchSetting);
if (!$provider) {
    http_response_code(200);
    echo json_encode(['status' => 'error', 'reason' => 'adapter_not_found']);
    exit;
}

if (!$provider->verifyWebhook($headers, (string) $rawBody, $payload, $_SERVER)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Webhook verification failed']));
}

$msgData = $provider->parseWebhook($payload);
if (!$msgData) {
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'no_message']);
    exit;
}

$from = (string) ($msgData['from'] ?? '');
$message = trim((string) ($msgData['message'] ?? ''));
$branchId = (int) ($branchSetting['branch_id'] ?? 0);

if ($from === '' || $message === '') {
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'empty_message']);
    exit;
}

try {
    $businessBranchId = $branchId;
    $sharedInbox = new WhatsAppSharedInboxService();
    $routing = $sharedInbox->resolveBranch('whatsapp_legacy', $branchId, $from, $message);

    if (($routing['handled'] ?? false) === true) {
        $reply = trim((string) ($routing['reply_message'] ?? ''));
        if ($reply !== '') {
            $provider->sendMessage($from, $reply);
            HookManager::doAction('channel.message_sent', $from, $reply, 'whatsapp_legacy', $branchId);
        }

        http_response_code(200);
        echo json_encode(['status' => 'processed', 'intent' => 'pilih_cabang']);
        exit;
    }

    $businessBranchId = (int) ($routing['branch_id'] ?? $branchId);

    $service = new CustomerConversationService();
    $result = $service->process('whatsapp_legacy', $businessBranchId, $from, $message);

    $reply = $result['reply_message'] ?? '';
    if (!empty($reply)) {
        $provider->sendMessage($from, $reply);
        HookManager::doAction('channel.message_sent', $from, $reply, 'whatsapp_legacy', $businessBranchId);
    }

    http_response_code(200);
    echo json_encode(['status' => 'processed', 'intent' => $result['intent'] ?? null]);
} catch (\Throwable $e) {
    error_log('[plugins/whatsapp-legacy/webhook] ' . $e->getMessage());
    http_response_code(200);
    echo json_encode(['status' => 'error']);
}
