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

    public function saveOrder(array $payload): void
    {
        $orderId = $payload['order_id'] ?? $payload['id'] ?? null;

        if (!$orderId) {
            throw new \RuntimeException('order_id required.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO tiktokshop_orders_sync
             (order_id, order_status, customer_name, total_amount, raw_payload, synced_at, created_at)
             VALUES
             (:order_id, :order_status, :customer_name, :total_amount, :raw_payload, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                order_status = VALUES(order_status),
                customer_name = VALUES(customer_name),
                total_amount = VALUES(total_amount),
                raw_payload = VALUES(raw_payload),
                synced_at = NOW()'
        );

        $stmt->execute([
            ':order_id' => $orderId,
            ':order_status' => $payload['order_status'] ?? $payload['status'] ?? null,
            ':customer_name' => $payload['customer_name'] ?? $payload['buyer_name'] ?? null,
            ':total_amount' => $payload['total_amount'] ?? $payload['payment']['total_amount'] ?? null,
            ':raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function logWebhook(?string $eventName, array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tiktokshop_webhook_logs
             (event_name, raw_payload, created_at)
             VALUES
             (:event_name, :raw_payload, NOW())'
        );

        $stmt->execute([
            ':event_name' => $eventName,
            ':raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function buildRealtimeStockPayload(int $menuItemId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT mi.id, mi.name,
                    tm.tiktok_product_id,
                    tm.tiktok_sku_id,
                    COALESCE(SUM(ms.qty), 0) AS total_stock
             FROM menu_items mi
             JOIN tiktokshop_product_mapping tm ON tm.menu_item_id = mi.id
             LEFT JOIN minimarket_inventory_stock ms ON ms.menu_item_id = mi.id
             WHERE mi.id = :menu_item_id
             GROUP BY mi.id, mi.name, tm.tiktok_product_id, tm.tiktok_sku_id
             LIMIT 1'
        );

        $stmt->execute([
            ':menu_item_id' => $menuItemId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \RuntimeException('TikTok Shop product mapping not found.');
        }

        return [
            'product_id' => $row['tiktok_product_id'],
            'skus' => [
                [
                    'sku_id' => $row['tiktok_sku_id'],
                    'stock_infos' => [
                        [
                            'available_stock' => max(0, (int)$row['total_stock']),
                        ],
                    ],
                ],
            ],
            'local' => [
                'menu_item_id' => (int)$row['id'],
                'name' => $row['name'],
                'total_stock' => (int)$row['total_stock'],
            ],
        ];
    }
}
