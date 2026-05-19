<?php

/**
 * Payment Gateway Notification Endpoint
 *
 * URL: POST /api/payment/notify.php?provider={nama}&branch={id}
 *
 * Endpoint generik yang meneruskan notifikasi ke plugin via HookManager.
 * Plugin (mis. MidtransPaymentPlugin) mendaftar ke action 'payment.notification'
 * dan menangani verifikasi + update order di sana.
 *
 * Midtrans Notification URL: .../notify.php?provider=midtrans&branch=1
 * Xendit     Notification URL: .../notify.php?provider=xendit&branch=1
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Plugin\HookManager;

// Selalu 200 agar gateway tidak melakukan retry tak terbatas
function paymentRespondAndExit(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    paymentRespondAndExit(['error' => 'Method not allowed'], 405);
}

$provider = strtolower(trim($_GET['provider'] ?? ''));
$branchId = (int)($_GET['branch']             ?? 0);

if ($provider === '') {
    paymentRespondAndExit(['error' => 'provider parameter is required'], 400);
}
if ($branchId <= 0) {
    paymentRespondAndExit(['error' => 'branch parameter is required'], 400);
}

$rawBody = (string) file_get_contents('php://input');

// Coba decode sebagai JSON; fallback ke $_POST untuk provider yang kirim form-encoded
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    $payload = $_POST ?: [];
}

// Serahkan ke plugin yang mendaftar ke hook ini
HookManager::doAction('payment.notification', $provider, $payload, $branchId);

paymentRespondAndExit(['status' => 'ok']);
