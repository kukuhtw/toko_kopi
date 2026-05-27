<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    $query = strtolower(trim((string)($_GET['q'] ?? '')));

    if ($query === '') {
        throw new RuntimeException('Query required.');
    }

    $pdo = Database::getInstance();

    $stmt = $pdo->prepare(
        "SELECT mi.id, mi.name, mi.description
         FROM menu_items mi
         WHERE LOWER(mi.name) LIKE :query
            OR LOWER(mi.description) LIKE :query
         LIMIT 20"
    );

    $stmt->execute([
        ':query' => '%' . $query . '%'
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'query' => $query,
        'results' => $rows,
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
