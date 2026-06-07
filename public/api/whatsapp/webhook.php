<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/runtime.php';

use App\WhatsAppProviders\{ProviderFactory, MetaCloudApiProvider};
use App\Services\{CustomerConversationService, WhatsAppSharedInboxService};
use App\Config\Database;
use App\Plugin\HookManager;

header('Content-Type: application/json');

function whatsappMovedChannelForAdapter(string $adapterClass): ?string
{
    return match ($adapterClass) {
        'VonageProvider' => 'whatsapp_vonage',
        'TwilioProvider' => 'whatsapp_twilio',
        'BaileysBridgeProvider' => 'whatsapp_baileys',
        'MessageBirdProvider' => 'whatsapp_messagebird',
        'FonnteProvider' => 'whatsapp',
        default => null,
    };
}

function delegateToChannelWebhook(string $channelName, int $branchId): never
{
    $_GET['channel'] = $channelName;
    $_GET['branch'] = (string) $branchId;
    require dirname(__DIR__) . '/channel/webhook.php';
    exit;
}

function delegateToMetaWebhook(?int $branchId = null): never
{
    if ($branchId !== null && $branchId > 0) {
        $_GET['branch'] = (string) $branchId;
    }
    require dirname(__DIR__) . '/plugins/whatsapp-meta/webhook.php';
    exit;
}

// ── Meta verification challenge ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hub_mode'])) {
    $db   = Database::getInstance();
    // Find the Meta provider setting by verify token
    $token = $_GET['hub_verify_token'] ?? '';
    $stmt  = $db->prepare(
        'SELECT bws.*, wp.adapter_class FROM branch_whatsapp_settings bws
         JOIN whatsapp_providers wp ON bws.provider_id = wp.id
         WHERE bws.webhook_token = ? AND bws.is_active = 1 LIMIT 1'
    );
    $stmt->execute([$token]);
    $setting = $stmt->fetch();

    if ($setting) {
        $movedChannel = whatsappMovedChannelForAdapter((string)($setting['adapter_class'] ?? ''));
        if ($movedChannel !== null) {
            delegateToChannelWebhook($movedChannel, (int)($setting['branch_id'] ?? 0));
        }
        delegateToMetaWebhook((int)($setting['branch_id'] ?? 0));
    }

    if ($setting && $_GET['hub_mode'] === 'subscribe') {
        echo $_GET['hub_challenge'] ?? '';
        exit;
    }
    http_response_code(403);
    exit('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true) ?? [];
$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if ($payload === [] && !empty($_POST)) {
    $payload = $_POST;
} elseif ($payload === [] && str_contains($contentType, 'application/x-www-form-urlencoded')) {
    parse_str($rawBody, $payload);
}
$headers = getallheaders() ?: [];

// ── Identify provider and branch ──────────────────────────────────────────────
// Strategy: check for provider-specific headers first, then payload structure

$branchSetting = null;
$adapterClass  = null;

// ── Primary: branch-specific URL (?branch=ID) ────────────────────────────────
$branchIdParam = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
if ($branchIdParam > 0) {
    $db   = Database::getInstance();
    $stmt = $db->prepare(
        'SELECT bws.*, wp.adapter_class
         FROM branch_whatsapp_settings bws
         JOIN whatsapp_providers wp ON bws.provider_id = wp.id
         WHERE bws.branch_id = ? AND bws.is_active = 1
         LIMIT 1'
    );
    $stmt->execute([$branchIdParam]);
    $row = $stmt->fetch();
    if ($row) {
        $movedChannel = whatsappMovedChannelForAdapter((string)($row['adapter_class'] ?? ''));
        if ($movedChannel !== null) {
            delegateToChannelWebhook($movedChannel, $branchIdParam);
        }
        delegateToMetaWebhook($branchIdParam);
    }
}

// ── Fallback: auto-detect from payload structure ──────────────────────────────
if (!$branchSetting) {
    if (!empty($payload['entry'][0]['changes'])) {
        delegateToMetaWebhook($branchIdParam > 0 ? $branchIdParam : null);
    } elseif (!empty($payload['data']['phone'])) {
        $adapterClass = 'WablasProvider';
        $waNumber     = $payload['device'] ?? $payload['data']['device'] ?? '';
        if ($waNumber) {
            $branchSetting = ProviderFactory::findByWaNumber($waNumber);
        }
    } elseif (!empty($payload['from'])) {
        $adapterClass = 'GenericWebhookProvider';
    }

    // Last resort: match by WA number in payload
    if (!$branchSetting) {
        $waNumber = $payload['device'] ?? $payload['to'] ?? $payload['data']['device'] ?? '';
        if ($waNumber) {
            $branchSetting = ProviderFactory::findByWaNumber($waNumber);
        }
    }
}

if (!$branchSetting || !$adapterClass) {
    if (!empty($payload['sender'])) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'fonnte_moved_to_plugin']);
        exit;
    }
    if (!empty($payload['AccountSid']) && (isset($payload['From']) || isset($payload['WaId']))) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'twilio_moved_to_plugin']);
        exit;
    }
    if (
        (!empty($payload['bridge']) && strtolower((string)$payload['bridge']) === 'baileys')
        || (!empty($payload['source']) && strtolower((string)$payload['source']) === 'baileys')
    ) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'baileys_moved_to_plugin']);
        exit;
    }
    if (!empty($payload['type']) && (string) $payload['type'] === 'message.created') {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'messagebird_moved_to_plugin']);
        exit;
    }
    if (($payload['channel'] ?? '') === 'whatsapp' && !empty($payload['from'])) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'vonage_moved_to_plugin']);
        exit;
    }

    http_response_code(200); // Always 200 for webhooks
    echo json_encode(['status' => 'ignored', 'reason' => 'branch_not_found']);
    exit;
}

// ── Build provider adapter ────────────────────────────────────────────────────
$provider = ProviderFactory::make($adapterClass, $branchSetting);
if (!$provider) {
    http_response_code(200);
    echo json_encode(['status' => 'error', 'reason' => 'adapter_not_found']);
    exit;
}

// ── Verify webhook ────────────────────────────────────────────────────────────
if (!$provider->verifyWebhook($headers, $rawBody, $payload, $_SERVER)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Webhook verification failed']));
}

// ── Parse message ─────────────────────────────────────────────────────────────
$msgData = $provider->parseWebhook($payload);
if (!$msgData) {
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'no_message']);
    exit;
}

$from    = $msgData['from'];
$message = trim($msgData['message']);
$branchId = (int) $branchSetting['branch_id'];

if (empty($message)) {
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'empty_message']);
    exit;
}

// ── Process through ChatbotEngine ─────────────────────────────────────────────
try {
    $businessBranchId = $branchId;
    $sharedInbox = new WhatsAppSharedInboxService();
    $routing = $sharedInbox->resolveBranch('whatsapp', $branchId, $from, $message);

    if (($routing['handled'] ?? false) === true) {
        $reply = trim((string) ($routing['reply_message'] ?? ''));
        if ($reply !== '') {
            $provider->sendMessage($from, $reply);
            HookManager::doAction('channel.message_sent', $from, $reply, 'whatsapp', $branchId);
        }

        http_response_code(200);
        echo json_encode(['status' => 'processed', 'intent' => 'pilih_cabang']);
        exit;
    }

    $businessBranchId = (int) ($routing['branch_id'] ?? $branchId);

    $service = new CustomerConversationService();
    $result = $service->process('whatsapp', $businessBranchId, $from, $message);

    $reply = $result['reply_message'] ?? '';
    if (!empty($reply)) {
        $provider->sendMessage($from, $reply);
        HookManager::doAction('channel.message_sent', $from, $reply, 'whatsapp', $businessBranchId);
    }

    http_response_code(200);
    echo json_encode(['status' => 'processed', 'intent' => $result['intent']]);
} catch (\Throwable $e) {
    error_log('[whatsapp/webhook] ' . $e->getMessage());
    http_response_code(200); // Must return 200 to avoid retries
    echo json_encode(['status' => 'error']);
}
