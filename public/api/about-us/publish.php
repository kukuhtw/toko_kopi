<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!$payload || empty($payload['id'])) {
        throw new RuntimeException('id required.');
    }

    $pdo = Database::getInstance();

    $stmt = $pdo->prepare(
        'UPDATE about_us_contents
         SET content_status = :content_status,
             published_at = NOW(),
             updated_at = NOW()
         WHERE id = :id'
    );

    $stmt->execute([
        ':content_status' => 'published',
        ':id' => $payload['id'],
    ]);

    echo json_encode([
        'success' => true,
        'status' => 'published',
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
