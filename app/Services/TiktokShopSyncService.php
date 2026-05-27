<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class TiktokShopSyncService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function saveSellerToken(array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tiktokshop_seller_tokens
             (seller_id, access_token, refresh_token, access_token_expire_in, refresh_token_expire_in, seller_name, created_at, updated_at)
             VALUES
             (:seller_id, :access_token, :refresh_token, :access_token_expire_in, :refresh_token_expire_in, :seller_name, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token),
                refresh_token = VALUES(refresh_token),
                access_token_expire_in = VALUES(access_token_expire_in),
                refresh_token_expire_in = VALUES(refresh_token_expire_in),
                seller_name = VALUES(seller_name),
                updated_at = NOW()'
        );

        $stmt->execute([
            ':seller_id' => $payload['seller_id'],
            ':access_token' => $payload['access_token'] ?? null,
            ':refresh_token' => $payload['refresh_token'] ?? null,
            ':access_token_expire_in' => $payload['access_token_expire_in'] ?? null,
            ':refresh_token_expire_in' => $payload['refresh_token_expire_in'] ?? null,
            ':seller_name' => $payload['seller_name'] ?? null,
        ]);
    }

    public function mapProduct(array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tiktokshop_product_mapping
             (menu_item_id, local_sku, local_barcode, tiktok_product_id, tiktok_sku_id, sync_status, last_sync_at, created_at, updated_at)
             VALUES
             (:menu_item_id, :local_sku, :local_barcode, :tiktok_product_id, :tiktok_sku_id, :sync_status, NOW(), NOW(), NOW())'
        );

        $stmt->execute([
            ':menu_item_id' => $payload['menu_item_id'],
            ':local_sku' => $payload['local_sku'] ?? null,
            ':local_barcode' => $payload['local_barcode'] ?? null,
            ':tiktok_product_id' => $payload['tiktok_product_id'] ?? null,
            ':tiktok_sku_id' => $payload['tiktok_sku_id'] ?? null,
            ':sync_status' => $payload['sync_status'] ?? 'mapped',
        ]);
    }
}
