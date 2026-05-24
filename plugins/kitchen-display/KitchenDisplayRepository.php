<?php

declare(strict_types=1);

class KitchenDisplayRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = \App\Config\Database::getInstance();
    }

    /** Semua order pending + processing yang SUDAH DIBAYAR untuk cabang ini, urut dari terlama */
    public function getActiveOrders(int $branchId): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.id, o.order_number, o.customer_name, o.channel,
                    o.total_amount, o.order_status, o.payment_status, o.notes,
                    o.fulfillment_type, o.table_number, o.created_at,
                    TIMESTAMPDIFF(SECOND, o.created_at, NOW()) AS elapsed_seconds
             FROM orders o
             WHERE o.branch_id = ?
               AND o.order_status IN ('pending', 'processing')
               AND o.payment_status = 'paid'
             ORDER BY o.created_at ASC"
        );
        $stmt->execute([$branchId]);

        return $this->attachItems($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /** Order completed hari ini, urut terbaru di atas */
    public function getRecentCompleted(int $branchId, int $limit = 25): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.id, o.order_number, o.customer_name, o.channel,
                    o.total_amount, o.order_status, o.payment_status, o.notes,
                    o.fulfillment_type, o.table_number, o.created_at,
                    TIMESTAMPDIFF(SECOND, o.created_at, NOW()) AS elapsed_seconds
             FROM orders o
             WHERE o.branch_id = ?
               AND o.order_status = 'completed'
               AND DATE(o.created_at) = CURDATE()
             ORDER BY o.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$branchId, $limit]);

        return $this->attachItems($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function attachItems(array $orders): array
    {
        if (empty($orders)) {
            return [];
        }

        $ids          = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $this->db->prepare(
            "SELECT order_id, menu_name, variant_label, quantity, notes
             FROM order_items
             WHERE order_id IN ({$placeholders})
             ORDER BY id ASC"
        );
        $stmt->execute($ids);

        $byOrder = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $byOrder[(int)$row['order_id']][] = $row;
        }

        foreach ($orders as &$o) {
            $o['items']           = $byOrder[(int)$o['id']] ?? [];
            $o['elapsed_seconds'] = (int)$o['elapsed_seconds'];
        }

        return $orders;
    }
}
