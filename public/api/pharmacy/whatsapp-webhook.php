<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Services\PharmacyAutoReplyService;
use App\Services\PharmacyWhatsappGatewayService;

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);

    $message = trim((string)($payload['message'] ?? ''));
    $phone = trim((string)($payload['phone'] ?? ''));

    if ($message === '' || $phone === '') {
        throw new RuntimeException('Phone and message are required.');
    }

    $autoReply = new PharmacyAutoReplyService();
    $gateway = new PharmacyWhatsappGatewayService();

    $reply = $autoReply->generateReply($message);

    $sendResult = $gateway->sendMessage(
        'fonnte',
        $phone,
        $reply['reply']
    );

    echo json_encode([
        'success' => true,
        'reply' => $reply,
        'gateway_result' => $sendResult,
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
