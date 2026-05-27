<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Services/ShopeeApiClient.php';

use App\Services\ShopeeApiClient;

header('Content-Type: application/json');

try {
    $client = new ShopeeApiClient();

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
