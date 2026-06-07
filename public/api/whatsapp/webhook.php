<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/runtime.php';

use App\Config\Database;

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

function delegateToLegacyWebhook(): never
{
    require dirname(__DIR__) . '/plugins/whatsapp-legacy/webhook.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hub_mode'])) {
    $db = Database::getInstance();
    $token = $_GET['hub_verify_token'] ?? '';
    $stmt = $db->prepare(
        'SELECT bws.*, wp.adapter_class FROM branch_whatsapp_settings bws
         JOIN whatsapp_providers wp ON bws.provider_id = wp.id
         WHERE bws.webhook_token = ? AND bws.is_active = 1 LIMIT 1'
    );
    $stmt->execute([$token]);
    $setting = $stmt->fetch();

    if ($setting) {
        $movedChannel = whatsappMovedChannelForAdapter((string) ($setting['adapter_class'] ?? ''));
        if ($movedChannel !== null) {
            delegateToChannelWebhook($movedChannel, (int) ($setting['branch_id'] ?? 0));
        }
        delegateToMetaWebhook((int) ($setting['branch_id'] ?? 0));
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
$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if ($payload === [] && !empty($_POST)) {
    $payload = $_POST;
} elseif ($payload === [] && str_contains($contentType, 'application/x-www-form-urlencoded')) {
    parse_str($rawBody, $payload);
}

$branchIdParam = isset($_GET['branch']) ? (int) $_GET['branch'] : 0;
if ($branchIdParam > 0) {
    $db = Database::getInstance();
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
        $movedChannel = whatsappMovedChannelForAdapter((string) ($row['adapter_class'] ?? ''));
        if ($movedChannel !== null) {
            delegateToChannelWebhook($movedChannel, $branchIdParam);
        }
        delegateToMetaWebhook($branchIdParam);
    }
}

if (!empty($payload['entry'][0]['changes'])) {
    delegateToMetaWebhook($branchIdParam > 0 ? $branchIdParam : null);
}

delegateToLegacyWebhook();
