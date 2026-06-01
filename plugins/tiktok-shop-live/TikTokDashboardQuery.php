<?php

declare(strict_types=1);

use App\Config\Database;

final class TikTokDashboardQuery
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getLatestMetrics(int $branchId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tiktok_live_metrics WHERE branch_id = ? ORDER BY created_at DESC LIMIT ' . (int)$limit);
        $stmt->execute([$branchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRecommendations(int $branchId, int $limit = 10): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tiktok_ai_recommendations WHERE branch_id = ? ORDER BY created_at DESC LIMIT ' . (int)$limit);
        $stmt->execute([$branchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSyncLogs(int $branchId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tiktok_sync_logs WHERE branch_id = ? ORDER BY created_at DESC LIMIT ' . (int)$limit);
        $stmt->execute([$branchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
