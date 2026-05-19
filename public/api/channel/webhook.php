<?php

/**
 * Generic webhook endpoint untuk channel yang didaftarkan via plugin.
 *
 * URL: POST /api/channel/webhook.php?channel={nama}&branch={id}
 *
 * Channel yang didaftarkan plugin masuk lewat endpoint generik ini.
 * Beberapa channel legacy masih bisa punya endpoint sendiri.
 *
 * Contoh penggunaan dari plugin LINE:
 *   Webhook URL di LINE Developer Console:
 *   https://yourdomain.com/api/channel/webhook.php?channel=line&branch=1
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Plugin\{ChannelRouter, HookManager};
use App\Services\{CustomerConversationService, WhatsAppSharedInboxService};

header('Content-Type: application/json; charset=utf-8');

// Selalu kembalikan 200 agar platform tidak retry terus-menerus
function respondAndExit(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function respondTextAndExit(string $text, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    respondAndExit(['error' => 'Method not allowed'], 405);
}

$channelName = strtolower(trim($_GET['channel'] ?? ''));
$branchId    = (int) ($_GET['branch'] ?? 0);

if ($channelName === '') {
    respondAndExit(['error' => 'channel parameter is required'], 400);
}

// ── Resolve channel dari registry plugin ─────────────────────────
$channel = ChannelRouter::get($channelName);
if ($channel === null) {
    respondAndExit(['status' => 'ignored', 'reason' => 'channel_not_registered']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (method_exists($channel, 'handleVerification')) {
        $challenge = $channel->handleVerification($_GET, $branchId);
        if ($challenge !== null) {
            respondTextAndExit($challenge, 200);
        }
    }
    respondAndExit(['error' => 'Webhook verification failed'], 403);
}

// ── Baca request ─────────────────────────────────────────────────
$rawBody = (string) file_get_contents('php://input');
$payload = json_decode($rawBody, true) ?? [];
$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

if ($payload === [] && !empty($_POST)) {
    $payload = $_POST;
} elseif ($payload === [] && str_contains($contentType, 'application/x-www-form-urlencoded')) {
    parse_str($rawBody, $payload);
}
$headers = getallheaders() ?: [];

if ($branchId <= 0 && method_exists($channel, 'resolveBranchId')) {
    $resolvedBranchId = $channel->resolveBranchId($headers, $payload, $rawBody, $_GET);
    if (is_int($resolvedBranchId) && $resolvedBranchId > 0) {
        $branchId = $resolvedBranchId;
    }
}

if ($branchId <= 0) {
    respondAndExit(['error' => 'branch parameter is required'], 400);
}

if (!$channel->isAvailable($branchId)) {
    respondAndExit(['status' => 'ignored', 'reason' => 'channel_not_configured_for_branch']);
}

// ── Verifikasi webhook ───────────────────────────────────────────
if (!$channel->verifyWebhook($headers, $rawBody)) {
    respondAndExit(['error' => 'Webhook verification failed'], 403);
}

if (method_exists($channel, 'respondToWebhook')) {
    $verificationResponse = $channel->respondToWebhook($payload, null, 'verified');
    if (is_array($verificationResponse)) {
        respondAndExit($verificationResponse, 200);
    }
}

// ── Parse pesan ──────────────────────────────────────────────────
$msgData = $channel->parseMessage($payload);
if ($msgData === null) {
    if (method_exists($channel, 'respondToWebhook')) {
        $emptyResponse = $channel->respondToWebhook($payload, null, 'no_message');
        if (is_array($emptyResponse)) {
            respondAndExit($emptyResponse, 200);
        }
    }
    respondAndExit(['status' => 'ignored', 'reason' => 'no_message']);
}

$from    = (string) ($msgData['from']    ?? '');
$message = trim((string) ($msgData['message'] ?? ''));

if ($from === '' || $message === '') {
    respondAndExit(['status' => 'ignored', 'reason' => 'empty_from_or_message']);
}

// ── Proses lewat ChatbotEngine ───────────────────────────────────
try {
    $businessBranchId = $branchId;
    if (method_exists($channel, 'resolveBusinessBranch')) {
        $routing = $channel->resolveBusinessBranch($branchId, $from, $message);

        if (($routing['handled'] ?? false) === true) {
            $reply = trim((string) ($routing['reply_message'] ?? ''));
            if ($reply !== '') {
                HookManager::doAction('channel.message_sent', $from, $reply, $channelName, $branchId);

                if (method_exists($channel, 'respondToWebhook')) {
                    $directResponse = $channel->respondToWebhook($payload, $reply, 'success');
                    if (is_array($directResponse)) {
                        respondAndExit($directResponse, 200);
                    }
                }

                $channel->sendMessage($from, $reply);
            }

            respondAndExit(['status' => 'processed', 'intent' => 'pilih_cabang']);
        }

        $businessBranchId = (int) ($routing['branch_id'] ?? $branchId);
    } elseif (str_starts_with($channelName, 'whatsapp')) {
        $sharedInbox = new WhatsAppSharedInboxService();
        $routing = $sharedInbox->resolveBranch($channelName, $branchId, $from, $message);

        if (($routing['handled'] ?? false) === true) {
            $reply = trim((string) ($routing['reply_message'] ?? ''));
            if ($reply !== '') {
                $channel->sendMessage($from, $reply);
                HookManager::doAction('channel.message_sent', $from, $reply, $channelName, $branchId);
            }

            respondAndExit(['status' => 'processed', 'intent' => 'pilih_cabang']);
        }

        $businessBranchId = (int) ($routing['branch_id'] ?? $branchId);
    }

    $service = new CustomerConversationService();
    $result = $service->process($channelName, $businessBranchId, $from, $message);

    $reply = trim((string) ($result['reply_message'] ?? ''));

    if (method_exists($channel, 'respondToWebhook')) {
        $directResponse = $channel->respondToWebhook($payload, $reply, 'success');
        if (is_array($directResponse)) {
            if ($reply !== '') {
                HookManager::doAction('channel.message_sent', $from, $reply, $channelName, $businessBranchId);
            }
            respondAndExit($directResponse, 200);
        }
    }

    if ($reply !== '') {
        // Action: pesan terkirim ke customer
        HookManager::doAction('channel.message_sent', $from, $reply, $channelName, $businessBranchId);

        $channel->sendMessage($from, $reply);
    }

    respondAndExit(['status' => 'processed', 'intent' => $result['intent'] ?? null]);

} catch (\Throwable $e) {
    error_log("[channel/{$channelName}/webhook] " . $e->getMessage());
    respondAndExit(['status' => 'error']);
}
