<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

header('Content-Type: application/json');

try {
    echo json_encode([
        'success' => true,
        'data' => [
            'total_products' => 120,
            'low_stock' => 14,
            'expired_soon' => 6,
            'today_sales' => 2450000,
            'monthly_sales' => 45250000,
            'top_products' => [
                'Paracetamol 500mg',
                'Vitamin C 1000mg',
                'OBH Combi'
            ]
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
