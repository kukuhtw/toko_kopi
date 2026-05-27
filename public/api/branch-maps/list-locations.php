<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    $pdo = Database::getInstance();

    $stmt = $pdo->query(
        'SELECT *
         FROM branch_maps_locations
         WHERE is_active = 1
         ORDER BY branch_name ASC'
    );

    echo json_encode([
        'success' => true,
        'total' => $stmt->rowCount(),
        'results' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
