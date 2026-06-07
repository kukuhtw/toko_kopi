<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/runtime.php';

use App\Models\CustomerModel;
use KopiBot\Core\Response;
use KopiBot\Domains\Branch\BranchRepository;
use KopiBot\Domains\Chatbot\ChatMessageDTO;
use KopiBot\Domains\Chatbot\ChatbotService;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::json([
        'success' => false,
        'message' => 'Method not allowed',
    ], 405);
    return;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    $body = $_POST;
}

$branchId = (int) ($body['branch_id'] ?? 0);
$message = trim((string) ($body['message'] ?? ''));
$sessionId = (string) ($body['session_id'] ?? $_COOKIE['chat_session'] ?? session_id());
$customerName = trim((string) ($body['customer_name'] ?? ''));
$customerEmail = trim((string) ($body['customer_email'] ?? ''));
$customerWhatsapp = preg_replace('/[^0-9+]/', '', (string) ($body['customer_whatsapp'] ?? ''));

if ($branchId <= 0) {
    Response::json([
        'success' => false,
        'message' => 'branch_id is required',
    ], 400);
    return;
}

$isRegisterPing = $message === '__register__';

if (!$isRegisterPing && $message === '') {
    Response::json([
        'success' => false,
        'message' => 'message is required',
    ], 400);
    return;
}

if (!$isRegisterPing && strlen($message) > 1000) {
    Response::json([
        'success' => false,
        'message' => 'Message too long (max 1000 chars)',
    ], 422);
    return;
}

$customerIdentifier = $sessionId !== '' ? $sessionId : session_id();
$customerModel = new CustomerModel();
$resolvedCustomer = $customerModel->resolveWebCustomer(
    $customerIdentifier,
    trim($customerName),
    $customerEmail,
    $customerWhatsapp
);

if ($isRegisterPing) {
    Response::json([
        'success' => true,
        'message' => 'OK',
        'data' => ['registered' => true],
    ]);
    return;
}

try {
    $branch = (new BranchRepository())->find($branchId) ?: [];
    $tenantId = max(1, (int) ($branch['tenant_id'] ?? 1));

    $chatbot = new ChatbotService();
    $result = $chatbot->process(new ChatMessageDTO(
        tenantId: $tenantId,
        branchId: $branchId,
        channel: 'web',
        senderId: $customerIdentifier,
        message: $message,
        customerId: (int) ($resolvedCustomer['id'] ?? 0)
    ));

    Response::json([
        'success' => true,
        'message' => 'OK',
        'data' => [
            'reply_message' => (string) ($result['message'] ?? ''),
            'intent' => $result['intent'] ?? null,
            'action_result' => $result['intent_result'] ?? $result['tasks'] ?? null,
            'conversation' => [
                'state' => $result['state'] ?? null,
            ],
            'chatbot' => $result,
        ],
    ]);
} catch (\Throwable $e) {
    $logLine = '[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n";
    file_put_contents(LOG_PATH . '/php_error.log', $logLine, FILE_APPEND | LOCK_EX);

    Response::json([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
    ], 500);
}
