<?php

declare(strict_types=1);

namespace KopiBot\Domains\Customer;

use KopiBot\Core\Database;
use PDO;

class FavoriteProductService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function topProducts(int $tenantId, int $customerId, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT oi.product_id, oi.product_name, COUNT(*) AS order_count, SUM(oi.qty) AS total_qty, MAX(o.created_at) AS last_order_at
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id AND oi.tenant_id = o.tenant_id
             WHERE o.tenant_id = :tenant_id AND o.customer_id = :customer_id
             GROUP BY oi.product_id, oi.product_name
             ORDER BY total_qty DESC, order_count DESC, last_order_at DESC
             LIMIT :limit'
        );

        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function formatFavorites(array $products): string
    {
        if (empty($products)) {
            return 'Belum ada produk favorit karena belum ada riwayat pembelian.';
        }

        $lines = [];
        foreach (array_values($products) as $index => $product) {
            $lines[] = sprintf(
                '%d. %s, total %dx dibeli',
                $index + 1,
                $product['product_name'],
                (int) $product['total_qty']
            );
        }

        return implode("\n", $lines);
    }
}
