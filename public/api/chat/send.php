<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\{Response, Sanitize};
use App\Models\{BranchModel, CustomerModel};
use KopiBot\Domains\Chatbot\{ChatbotService, ChatMessageDTO};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

$branchId = (int)($body['branch_id'] ?? Sanitize::post('branch_id', 'int'));
$message = trim((string)($body['message'] ?? Sanitize::post('message') ?? ''));
$sessionId = (string)($body['session_id'] ?? $_COOKIE['chat_session'] ?? session_id());
$customerName = trim((string)($body['customer_name'] ?? ''));
$customerEmail = trim((string)($body['customer_email'] ?? ''));
$customerWhatsapp = preg_replace('/[^0-9+]/', '', (string)($body['customer_whatsapp'] ?? ''));

if (!$branchId) {
    Response::error('branch_id is required');
}

$isRegisterPing = $message === '__register__';

if (!$isRegisterPing && $message === '') {
    Response::error('message is required');
}
if (!$isRegisterPing && strlen($message) > 1000) {
    Response::error('Message too long (max 1000 chars)');
}

$customerIdentifier = $sessionId ?: session_id();
$customerModel = new CustomerModel();
$resolvedCustomer = $customerModel->resolveWebCustomer(
    $customerIdentifier,
    Sanitize::string($customerName),
    $customerEmail,
    $customerWhatsapp
);

if ($isRegisterPing) {
    Response::success(['registered' => true], 'OK');
}

try {
    $branch = (new BranchModel())->find($branchId) ?: [];
    $tenantId = (int)($branch['tenant_id'] ?? 1);
    if ($tenantId <= 0) {
        $tenantId = 1;
    }

    $chatbot = new ChatbotService();
    $result = $chatbot->process(new ChatMessageDTO(
        tenantId: $tenantId,
        branchId: $branchId,
        channel: 'web',
        senderId: $customerIdentifier,
        message: $message,
        customerId: (int)($resolvedCustomer['id'] ?? 0)
    ));

    Response::success([
        'reply_message' => (string)($result['message'] ?? ''),
        'intent' => $result['intent'] ?? null,
        'action_result' => $result['intent_result'] ?? $result['tasks'] ?? null,
        'conversation' => [
            'state' => $result['state'] ?? null,
        ],
        'chatbot' => $result,
    ], 'OK');
} catch (\Throwable $e) {
    $logLine = '[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n";
    file_put_contents(dirname(__DIR__, 3) . '/storage/logs/php_error.log', $logLine, FILE_APPEND | LOCK_EX);
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
