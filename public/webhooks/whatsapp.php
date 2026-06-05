<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use KopiBot\Channels\WhatsApp\IncomingMessageDTO;
use KopiBot\Channels\WhatsApp\WhatsAppWebhookService;
use KopiBot\Core\Request;
use KopiBot\Core\Response;

$request = new Request();
$payload = $request->all();

$tenantId = (int) ($payload['tenant_id'] ?? 1);
$branchId = (int) ($payload['branch_id'] ?? 1);
$phone = (string) ($payload['phone'] ?? $payload['sender'] ?? $payload['from'] ?? '');
$message = (string) ($payload['message'] ?? $payload['text'] ?? $payload['body'] ?? '');
$senderId = (string) ($payload['sender_id'] ?? $phone ?: 'whatsapp-user');
$sendReply = filter_var($payload['send_reply'] ?? true, FILTER_VALIDATE_BOOLEAN);

if ($phone === '' || $message === '') {
    Response::json([
        'success' => false,
        'message' => 'phone and message are required',
        'payload' => $payload,
    ], 422);
    return;
}

$result = (new WhatsAppWebhookService())->handle(new IncomingMessageDTO(
    tenantId: $tenantId,
    branchId: $branchId,
    senderId: $senderId,
    phone: $phone,
    message: $message,
    rawPayload: json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
), $sendReply);

Response::json($result);
