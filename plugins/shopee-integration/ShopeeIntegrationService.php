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

    public function generateAuthUrl(?int $branchId = null): array
    {
        $partnerId = $branchId !== null && $branchId > 0
            ? $this->repo->getBranchSetting($branchId, 'partner_id', '')
            : '';
        $partnerId = $partnerId !== '' ? $partnerId : (string) env_value('SHOPEE_PARTNER_ID', '');

        $redirect = $branchId !== null && $branchId > 0
            ? BASE_URL . '/api/plugins/shopee/callback.php?branch=' . $branchId
            : (string) env_value('SHOPEE_REDIRECT_URL', BASE_URL . '/api/plugins/shopee/callback.php');

        if ($partnerId === '') {
            return [
                'success' => false,
                'message' => 'Shopee partner_id belum dikonfigurasi.',
            ];
        }

        $baseUrl = rtrim((string) env_value('SHOPEE_BASE_URL', 'https://partner.shopeemobile.com'), '/');
        $timestamp = time();
        $authUrl = $baseUrl
            . '/api/v2/shop/auth_partner?partner_id=' . urlencode($partnerId)
            . '&timestamp=' . $timestamp
            . '&redirect=' . urlencode($redirect);

        return [
            'success' => true,
            'auth_url' => $authUrl,
            'partner_id' => $partnerId,
            'redirect' => $redirect,
            'mode' => 'scaffold',
        ];
    }

    public function handleCallback(int $branchId, array $payload): array
    {
        if (empty($payload['shop_id'])) {
            return [
                'success' => false,
                'message' => 'shop_id missing.',
            ];
        }

        if ($branchId > 0) {
            $this->repo->saveBranchTokens($branchId, $payload);
        } else {
            $this->repo->saveGlobalTokens($payload);
        }

        $logId = $this->repo->queueLog(
            $branchId > 0 ? $branchId : 0,
            null,
            'oauth.callback',
            'auth',
            'queued',
            $payload,
            'callback:' . (string) $payload['shop_id']
        );
        $response = [
            'success' => true,
            'shop_id' => (string) $payload['shop_id'],
            'mode' => 'scaffold',
            'message' => 'Shopee callback scaffold processed.',
        ];
        $this->repo->markLogProcessed($logId, 'success', $response, 200, (string) $payload['shop_id']);

        return $response;
    }

    public function mapProduct(int $branchId, array $payload): array
    {
        if (empty($payload['menu_item_id'])) {
            return [
                'success' => false,
                'message' => 'menu_item_id required.',
            ];
        }

        $this->repo->upsertProductMapping($branchId, $payload);
        $logId = $this->repo->queueLog(
            $branchId > 0 ? $branchId : 0,
            null,
            'product.map',
            'product',
            'queued',
            $payload,
            'product-map:' . (string) $payload['menu_item_id']
        );
        $response = [
            'success' => true,
            'message' => 'Shopee product mapping saved.',
            'mode' => 'plugin_adapter',
        ];
        $this->repo->markLogProcessed($logId, 'success', $response, 200, (string) ($payload['shopee_item_id'] ?? ''));

        return $response;
    }

    public function ingestOrder(int $branchId, array $payload): array
    {
        if (empty($payload['order_sn'])) {
            return [
                'success' => false,
                'message' => 'order_sn required.',
            ];
        }

        $this->repo->saveOrderSnapshot($branchId, $payload);
        $logId = $this->repo->queueLog(
            $branchId > 0 ? $branchId : 0,
            null,
            'order.sync',
            'order',
            'queued',
            $payload,
            'order:' . (string) $payload['order_sn']
        );
        $response = [
            'success' => true,
            'order_sn' => (string) $payload['order_sn'],
            'mode' => 'plugin_adapter',
            'message' => 'Shopee order snapshot saved.',
        ];
        $this->repo->markLogProcessed($logId, 'success', $response, 200, (string) $payload['order_sn']);

        return $response;
    }

    public function buildStockPayload(int $menuItemId): array
    {
        $payload = $this->repo->getStockPayload($menuItemId);
        if ($payload === null) {
            return [
                'success' => false,
                'message' => 'Shopee mapping not found.',
            ];
        }

        return [
            'success' => true,
            'payload' => $payload,
            'mode' => 'plugin_adapter',
        ];
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
