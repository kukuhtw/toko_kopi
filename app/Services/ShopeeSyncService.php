<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class ShopeeSyncService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function saveShopToken(array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO shopee_shop_tokens
             (shop_id, access_token, refresh_token, expire_in, refresh_token_expire_in, merchant_name, created_at, updated_at)
             VALUES
             (:shop_id, :access_token, :refresh_token, :expire_in, :refresh_token_expire_in, :merchant_name, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token),
                refresh_token = VALUES(refresh_token),
                expire_in = VALUES(expire_in),
                refresh_token_expire_in = VALUES(refresh_token_expire_in),
                merchant_name = VALUES(merchant_name),
                updated_at = NOW()'
        );

        $stmt->execute([
            ':shop_id' => $payload['shop_id'],
            ':access_token' => $payload['access_token'] ?? null,
            ':refresh_token' => $payload['refresh_token'] ?? null,
            ':expire_in' => $payload['expire_in'] ?? null,
            ':refresh_token_expire_in' => $payload['refresh_token_expire_in'] ?? null,
            ':merchant_name' => $payload['merchant_name'] ?? null,
        ]);
    }

    public function mapProduct(array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO shopee_product_mapping
             (menu_item_id, local_sku, local_barcode, shopee_item_id, shopee_model_id, sync_status, last_sync_at, created_at, updated_at)
             VALUES
             (:menu_item_id, :local_sku, :local_barcode, :shopee_item_id, :shopee_model_id, :sync_status, NOW(), NOW(), NOW())'
        );

        $stmt->execute([
            ':menu_item_id' => $payload['menu_item_id'],
            ':local_sku' => $payload['local_sku'] ?? null,
            ':local_barcode' => $payload['local_barcode'] ?? null,
            ':shopee_item_id' => $payload['shopee_item_id'] ?? null,
            ':shopee_model_id' => $payload['shopee_model_id'] ?? null,
            ':sync_status' => $payload['sync_status'] ?? 'mapped',
        ]);
    }

    public function saveOrder(array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO shopee_orders_sync
             (order_sn, order_status, customer_name, total_amount, raw_payload, synced_at, created_at)
             VALUES
             (:order_sn, :order_status, :customer_name, :total_amount, :raw_payload, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                order_status = VALUES(order_status),
                customer_name = VALUES(customer_name),
                total_amount = VALUES(total_amount),
                raw_payload = VALUES(raw_payload),
                synced_at = NOW()'
        );

        $stmt->execute([
            ':order_sn' => $payload['order_sn'],
            ':order_status' => $payload['order_status'] ?? null,
            ':customer_name' => $payload['customer_name'] ?? null,
            ':total_amount' => $payload['total_amount'] ?? null,
            ':raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function logWebhook(?string $eventName, array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO shopee_webhook_logs
             (event_name, raw_payload, created_at)
             VALUES
             (:event_name, :raw_payload, NOW())'
        );

        $stmt->execute([
            ':event_name' => $eventName,
            ':raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
