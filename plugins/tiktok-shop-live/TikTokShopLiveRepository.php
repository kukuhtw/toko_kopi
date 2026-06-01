<?php

declare(strict_types=1);

use App\Config\Database;

final class TikTokShopLiveRepository
{
    public const PLUGIN_SLUG='tiktok-shop-live';

    private PDO $db;

    public function __construct()
    {
        $this->db=Database::getInstance();
    }

    public function ensureSchema(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS tiktok_live_metrics (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            branch_id INT UNSIGNED NOT NULL,
            live_id VARCHAR(120) NOT NULL,
            viewers INT DEFAULT 0,
            likes_count INT DEFAULT 0,
            orders_count INT DEFAULT 0,
            revenue_amount DECIMAL(12,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tiktok_live(branch_id,live_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}
