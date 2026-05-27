<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    $businessId = (int)($_GET['business_id'] ?? 0);
    $branchId = (int)($_GET['branch_id'] ?? 0);

    $pdo = Database::getInstance();

    if ($branchId > 0) {
        $stmt = $pdo->prepare(
            'SELECT * FROM about_us_contents WHERE branch_id = :branch_id ORDER BY id DESC LIMIT 1'
        );

        $stmt->execute([
            ':branch_id' => $branchId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM about_us_contents WHERE business_id = :business_id ORDER BY id DESC LIMIT 1'
        );

        $stmt->execute([
            ':business_id' => $businessId,
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
