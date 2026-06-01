<?php

declare(strict_types=1);

final class TikTokInternalOrderMapper
{
    public function map(array $payload, int $branchId): array
    {
        $orderId = (string)($payload['order_id'] ?? $payload['id'] ?? $payload['data']['order_id'] ?? '');
        $customerName = (string)($payload['customer_name'] ?? $payload['buyer_name'] ?? $payload['recipient_address']['name'] ?? 'TikTok Customer');
        $phone = (string)($payload['phone'] ?? $payload['recipient_address']['phone'] ?? '');
        $amount = (float)($payload['total_amount'] ?? $payload['payment']['total_amount'] ?? 0);
        $status = (string)($payload['status'] ?? $payload['order_status'] ?? 'pending');

        return [
            'branch_id' => $branchId,
            'external_channel' => 'tiktok_shop_live',
            'external_order_id' => $orderId,
            'customer' => [
                'name' => $customerName,
                'phone' => $phone,
            ],
            'order' => [
                'order_number' => 'TTS-' . ($orderId !== '' ? $orderId : time()),
                'total_amount' => $amount,
                'status' => $this->mapStatus($status),
                'source' => 'tiktok_shop_live',
            ],
            'items' => $payload['items'] ?? $payload['line_items'] ?? [],
            'raw_payload' => $payload,
        ];
    }

    private function mapStatus(string $status): string
    {
        $status = strtolower($status);
        return match ($status) {
            'paid', 'awaiting_shipment', 'processing' => 'processing',
            'completed', 'delivered' => 'completed',
            'cancelled', 'canceled' => 'cancelled',
            default => 'pending',
        };
    }
}
