<?php

declare(strict_types=1);

final class ShopeeIntegrationService
{
    public function __construct(
        private ShopeeIntegrationRepository $repo
    ) {}

    public function queueOrderSync(array $order, string $eventName = 'order.created'): void
    {
        $branchId = (int) ($order['branch_id'] ?? 0);
        if ($branchId <= 0) {
            return;
        }

        $orderId = isset($order['id']) ? (int) $order['id'] : null;
        $syncKey = 'order:' . ($orderId ?: uniqid('', true));
        $logId = $this->repo->queueLog($branchId, $orderId, $eventName, 'order', 'queued', $order, $syncKey);

        $this->repo->markLogProcessed($logId, 'pending', [
            'message' => 'Shopee order sync scaffold queued. Real API integration is not implemented yet.',
        ]);
    }

    public function simulateProductSync(int $branchId, array $products = []): array
    {
        $logId = $this->repo->queueLog($branchId, null, 'product.sync', 'product', 'queued', [
            'count' => count($products),
        ], 'product:' . $branchId . ':' . date('YmdHis'));

        $response = [
            'success' => true,
            'mode' => 'scaffold',
            'message' => 'Shopee product sync placeholder executed.',
            'products_count' => count($products),
        ];

        $this->repo->markLogProcessed($logId, 'success', $response, 200);

        return $response;
    }

    public function simulateStockSync(int $branchId, array $stockPayload = []): array
    {
        $logId = $this->repo->queueLog($branchId, null, 'stock.sync', 'stock', 'queued', $stockPayload);
        $response = [
            'success' => true,
            'mode' => 'scaffold',
            'message' => 'Shopee stock sync placeholder executed.',
        ];
        $this->repo->markLogProcessed($logId, 'success', $response, 200);

        return $response;
    }

    public function handleWebhook(int $branchId, array|string|null $payload): array
    {
        $data = is_array($payload) ? $payload : ['raw' => (string) $payload];
        $topic = is_array($payload) ? (string) ($payload['type'] ?? $payload['topic'] ?? 'unknown') : 'unknown';
        $eventId = is_array($payload) ? (string) ($payload['event_id'] ?? $payload['id'] ?? '') : '';

        $this->repo->addWebhookAudit(
            $branchId,
            $topic !== '' ? $topic : null,
            $eventId !== '' ? $eventId : null,
            'unchecked',
            $data,
            'Webhook scaffold received. Signature verification and mapping are not implemented yet.'
        );

        return [
            'success' => true,
            'mode' => 'scaffold',
            'message' => 'Shopee webhook scaffold received.',
            'topic' => $topic,
            'event_id' => $eventId,
        ];
    }
}
