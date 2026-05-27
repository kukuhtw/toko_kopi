<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    $branchId = (int)($_GET['branch_id'] ?? 0);
    $branchCode = trim((string)($_GET['branch_code'] ?? ''));

    if ($branchId <= 0 && $branchCode === '') {
        throw new RuntimeException('branch_id or branch_code required.');
    }

    $pdo = Database::getInstance();

    if ($branchId > 0) {
        $stmt = $pdo->prepare(
            'SELECT * FROM branch_maps_locations WHERE branch_id = :branch_id LIMIT 1'
        );

        $stmt->execute([
            ':branch_id' => $branchId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM branch_maps_locations WHERE branch_code = :branch_code LIMIT 1'
        );

        $stmt->execute([
            ':branch_code' => $branchCode,
        ]);
    }

    echo json_encode([
        'success' => true,
        'data' => $stmt->fetch(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
