<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    $pdo = Database::getInstance();

    $stmt = $pdo->query(
        "SELECT id, title, slug, summary, banner_image, promo_code, discount_type, discount_value, start_date, end_date, published_at
         FROM promo_contents
         WHERE publish_status = 'published'
         ORDER BY published_at DESC"
    );

    echo json_encode([
        'success' => true,
        'results' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
