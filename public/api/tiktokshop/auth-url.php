<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Services/TiktokShopApiClient.php';

use App\Services\TiktokShopApiClient;

header('Content-Type: application/json');

try {
    $client = new TiktokShopApiClient();

    echo json_encode([
        'success' => true,
        'auth_url' => $client->generateAuthUrl(),
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
