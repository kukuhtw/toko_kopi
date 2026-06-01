<?php

declare(strict_types=1);

final class ShopeeOrderMapper
{
    public function map(array $payload, int $branchId): array
    {
        $orderSn = (string)($payload['order_sn'] ?? $payload['ordersn'] ?? $payload['order_id'] ?? '');

        return [
            'branch_id' => $branchId,
            'external_channel' => 'shopee_live',
            'external_order_id' => $orderSn,
            'customer' => [
                'name' => (string)($payload['customer_name'] ?? $payload['buyer_username'] ?? 'Shopee Customer'),
            ],
            'order' => [
                'order_number' => 'SHP-' . ($orderSn !== '' ? $orderSn : time()),
                'total_amount' => (float)($payload['total_amount'] ?? 0),
                'status' => (string)($payload['status'] ?? 'pending'),
                'source' => 'shopee_live',
            ],
            'items' => $payload['items'] ?? [],
            'raw_payload' => $payload,
        ];
    }
}
