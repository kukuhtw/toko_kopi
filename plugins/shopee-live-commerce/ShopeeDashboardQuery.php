<?php

declare(strict_types=1);

use App\Config\Database;

final class ShopeeDashboardQuery
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getLatestMetrics(int $branchId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM shopee_live_metrics WHERE branch_id = ? ORDER BY created_at DESC LIMIT ' . (int)$limit);
        $stmt->execute([$branchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRecentOrders(int $branchId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM shopee_orders WHERE branch_id = ? ORDER BY created_at DESC LIMIT ' . (int)$limit);
        $stmt->execute([$branchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSyncLogs(int $branchId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM shopee_sync_logs WHERE branch_id = ? ORDER BY created_at DESC LIMIT ' . (int)$limit);
        $stmt->execute([$branchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
