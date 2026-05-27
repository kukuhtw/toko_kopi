<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    $pdo = Database::getInstance();

    $totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM menu_items')->fetchColumn();

    $lowStock = (int)$pdo->query(
        'SELECT COUNT(*)
         FROM pharmacy_inventory_stock
         WHERE stock_qty <= minimum_stock_qty
           AND is_active = 1'
    )->fetchColumn();

    $expiredSoon = (int)$pdo->query(
        'SELECT COUNT(*)
         FROM pharmacy_inventory_stock
         WHERE expired_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
           AND is_active = 1'
    )->fetchColumn();

    $todaySales = (float)$pdo->query(
        'SELECT COALESCE(SUM(grand_total),0)
         FROM pharmacy_pos_sales
         WHERE DATE(created_at) = CURDATE()'
    )->fetchColumn();

    $monthlySales = (float)$pdo->query(
        'SELECT COALESCE(SUM(grand_total),0)
         FROM pharmacy_pos_sales
         WHERE YEAR(created_at) = YEAR(CURDATE())
           AND MONTH(created_at) = MONTH(CURDATE())'
    )->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => [
            'total_products' => $totalProducts,
            'low_stock' => $lowStock,
            'expired_soon' => $expiredSoon,
            'today_sales' => $todaySales,
            'monthly_sales' => $monthlySales,
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
