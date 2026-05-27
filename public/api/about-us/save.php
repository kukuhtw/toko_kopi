<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!$payload || empty($payload['title'])) {
        throw new RuntimeException('title required.');
    }

    $pdo = Database::getInstance();

    $stmt = $pdo->prepare(
        'INSERT INTO about_us_contents
         (business_id, branch_id, title, short_description, content, content_status, created_by, updated_by, created_at, updated_at)
         VALUES
         (:business_id, :branch_id, :title, :short_description, :content, :content_status, :created_by, :updated_by, NOW(), NOW())'
    );

    $stmt->execute([
        ':business_id' => $payload['business_id'] ?? null,
        ':branch_id' => $payload['branch_id'] ?? null,
        ':title' => $payload['title'],
        ':short_description' => $payload['short_description'] ?? null,
        ':content' => $payload['content'] ?? null,
        ':content_status' => $payload['content_status'] ?? 'draft',
        ':created_by' => $payload['created_by'] ?? 'system',
        ':updated_by' => $payload['updated_by'] ?? 'system',
    ]);

    echo json_encode([
        'success' => true,
        'id' => $pdo->lastInsertId(),
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
