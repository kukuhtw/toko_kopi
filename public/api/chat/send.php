<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\{Response, Sanitize};
use App\Services\CustomerConversationService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$raw     = file_get_contents('php://input');
$body    = json_decode($raw, true) ?? [];

$branchId      = (int) ($body['branch_id']      ?? Sanitize::post('branch_id', 'int'));
$message       = trim($body['message']           ?? Sanitize::post('message')    ?? '');
$sessionId     = $body['session_id']             ?? $_COOKIE['chat_session'] ?? session_id();
$customerName     = trim($body['customer_name']      ?? '');
$customerEmail    = trim($body['customer_email']     ?? '');
$customerWhatsapp = preg_replace('/[^0-9+]/', '', $body['customer_whatsapp'] ?? '');

if (!$branchId) {
    Response::error('branch_id is required');
}

// Silently ignore internal registration ping
$isRegisterPing = $message === '__register__';

if (!$isRegisterPing && empty($message)) {
    Response::error('message is required');
}
if (!$isRegisterPing && strlen($message) > 1000) {
    Response::error('Message too long (max 1000 chars)');
}

// Use session_id as web customer identifier
$customerIdentifier = $sessionId ?: session_id();
$customerModel = new \App\Models\CustomerModel();
$customerModel->resolveWebCustomer(
    $customerIdentifier,
    Sanitize::string($customerName),
    $customerEmail,
    $customerWhatsapp
);

// Return early for register ping — nothing to process through chatbot
if ($isRegisterPing) {
    Response::success(['registered' => true], 'OK');
}

try {
    $service = new CustomerConversationService();
    $result = $service->process('web', $branchId, $customerIdentifier, $message);
    Response::success($result, 'OK');
} catch (\Throwable $e) {
    $logLine = '[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage()
             . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n";
    file_put_contents(dirname(__DIR__, 3) . '/storage/logs/php_error.log', $logLine, FILE_APPEND | LOCK_EX);
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
