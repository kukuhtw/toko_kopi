<?php

declare(strict_types=1);

use App\Models\MenuModel;
use App\Models\OrderModel;

final class MokaConnectService
{
    private static array $suppressedOrderIds = [];

    private MokaConnectRepository $repo;
    private MokaConnectClient $client;

    public function __construct(?MokaConnectRepository $repo = null, ?MokaConnectClient $client = null)
    {
        $this->repo = $repo ?? new MokaConnectRepository();
        $this->client = $client ?? new MokaConnectClient($this->repo);
    }

    public function isEnabled(int $branchId): bool
    {
        return $branchId > 0 && $this->repo->getBranchSetting($branchId, 'is_active', '0') === '1';
    }

    public function shouldSuppressOutboundForOrder(int $orderId): bool
    {
        return $orderId > 0 && isset(self::$suppressedOrderIds[$orderId]);
    }

    public function queueOrderSync(array $order, string $eventName): void
    {
        $order = $this->enrichOrder($order);
        $orderId = (int)($order['id'] ?? 0);
        if ($this->shouldSuppressOutboundForOrder($orderId)) {
            return;
        }

        $branchId = (int)($order['branch_id'] ?? 0);
        if (!$this->isEnabled($branchId)) {
            return;
        }

        $payload = $this->buildOrderPayload($branchId, $order);
        $status = $this->hasConnectionConfig($branchId) ? 'pending' : 'config_missing';
        $logId = $this->repo->queueSync(
            $branchId,
            'order',
            $eventName,
            $status,
            (string)($order['order_number'] ?? $orderId),
            [
                'mapping' => $this->getMappingPreview($branchId)['order'],
                'request_template' => $this->client->buildOrderUpsertRequest($branchId, $payload),
                'order_payload' => $payload,
            ],
            $status === 'pending'
                ? ['message' => 'Order queued for live Moka sync.']
                : ['message' => 'Moka credentials are incomplete.'],
            'outbound',
            $orderId,
            'order:' . (string)($order['order_number'] ?? $orderId)
        );

        $this->repo->upsertOrderSyncStatus(
            $branchId,
            $orderId,
            (string)($order['order_number'] ?? ''),
            [
                'last_status' => $status,
                'last_event_name' => $eventName,
                'last_attempt_count' => 0,
                'last_error' => $status === 'config_missing' ? 'Moka credential belum lengkap.' : null,
                'last_log_id' => $logId,
                'last_queued_at' => date('Y-m-d H:i:s'),
                'last_synced_at' => null,
            ]
        );

        if ($status === 'pending' && $this->client->getBranchConfig($branchId)['live_order_push']) {
            $this->pushQueuedLog($logId);
        }
    }

    public function syncProductsSnapshot(int $branchId): void
    {
        if (!$this->isEnabled($branchId)) {
            return;
        }

        $menuModel = new MenuModel();
        $items = $menuModel->getMenuForBranch($branchId);
        $request = $this->client->buildProductsPullRequest($branchId);
        $request['preview_catalog'] = array_slice(array_map(function (array $item): array {
            return $this->buildCatalogItemPayload($item);
        }, $items), 0, 10);

        $status = $this->hasConnectionConfig($branchId) ? 'pending' : 'config_missing';
        $this->repo->queueSync(
            $branchId,
            'product',
            'manual.catalog_snapshot',
            $status,
            'branch:' . $branchId,
            [
                'mapping' => $this->getMappingPreview($branchId)['catalog'],
                'request_template' => $request,
            ],
            ['message' => 'Catalog snapshot prepared.']
        );
    }

    public function pullProductsLive(int $branchId): array
    {
        if (!$this->isEnabled($branchId)) {
            return ['success' => false, 'message' => 'Plugin Moka belum aktif untuk cabang ini.'];
        }

        $config = $this->client->getBranchConfig($branchId);
        if (!$config['live_catalog_pull']) {
            return ['success' => false, 'message' => 'Live catalog pull dimatikan di pengaturan cabang.'];
        }

        $result = $this->client->pullProducts($branchId);
        $responseData = is_array($result['data']) ? $result['data'] : ['raw' => $result['data']];
        $catalogSummary = $this->summarizeCatalogResponse($responseData);
        $this->repo->queueSync(
            $branchId,
            'product',
            'manual.catalog_pull_live',
            $result['success'] ? 'success' : 'failed',
            'branch:' . $branchId,
            [
                'mapping' => $this->getMappingPreview($branchId)['catalog'],
                'request' => $result['request_preview'],
            ],
            [
                'summary' => $catalogSummary,
                'response' => $result['response_preview'],
            ],
            'outbound',
            null,
            'catalog:' . $branchId,
            1,
            $result['status'],
            null,
            $result['error'] !== '' ? $result['error'] : null
        );

        return [
            'success' => $result['success'],
            'message' => $result['success']
                ? ('Catalog Moka berhasil diambil. ' . $catalogSummary['summary_text'])
                : ('Catalog pull gagal: ' . ($result['error'] ?: ('HTTP ' . (string)$result['status']))),
        ];
    }

    public function syncCustomersSnapshot(int $branchId, int $limit = 50): void
    {
        if (!$this->isEnabled($branchId)) {
            return;
        }

        $limit = max(1, min(200, $limit));
        $rows = \App\Config\Database::getInstance()->prepare(
            'SELECT c.id, c.name, c.email, c.whatsapp, c.channel, MAX(o.created_at) AS last_order_at
             FROM customers c
             JOIN orders o ON o.customer_id = c.id
             WHERE o.branch_id = ?
             GROUP BY c.id, c.name, c.email, c.whatsapp, c.channel
             ORDER BY last_order_at DESC
             LIMIT ?'
        );
        $rows->bindValue(1, $branchId, PDO::PARAM_INT);
        $rows->bindValue(2, $limit, PDO::PARAM_INT);
        $rows->execute();
        $customers = $rows->fetchAll();

        $request = $this->client->buildCustomersPullRequest($branchId);
        $request['preview_customers'] = array_slice($customers, 0, 10);
        $status = $this->hasConnectionConfig($branchId) ? 'pending' : 'config_missing';

        $this->repo->queueSync(
            $branchId,
            'customer',
            'manual.customer_snapshot',
            $status,
            'branch:' . $branchId,
            $request,
            ['message' => 'Customer snapshot prepared.']
        );
    }

    public function syncOutletsSnapshot(int $branchId): void
    {
        if (!$this->isEnabled($branchId)) {
            return;
        }

        $request = $this->client->buildOutletsPullRequest($branchId);
        $status = $this->hasConnectionConfig($branchId) ? 'pending' : 'config_missing';
        $this->repo->queueSync(
            $branchId,
            'outlet',
            'manual.outlet_snapshot',
            $status,
            'branch:' . $branchId,
            $request,
            ['message' => 'Outlet snapshot prepared.']
        );
    }

    public function syncRecentOrdersSnapshot(int $branchId, int $limit = 20): void
    {
        if (!$this->isEnabled($branchId)) {
            return;
        }

        $orders = (new OrderModel())->getByBranch($branchId, max(1, min(100, $limit)), 0);
        $status = $this->hasConnectionConfig($branchId) ? 'pending' : 'config_missing';
        $this->repo->queueSync(
            $branchId,
            'order',
            'manual.order_snapshot',
            $status,
            'branch:' . $branchId,
            [
                'mapping' => $this->getMappingPreview($branchId)['order'],
                'preview_orders' => array_slice(array_map(function (array $order) use ($branchId): array {
                    return $this->buildOrderPayload($branchId, $order);
                }, $orders), 0, 5),
            ],
            ['message' => 'Recent order payload snapshot prepared.']
        );
    }

    public function testConnection(int $branchId): array
    {
        $result = $this->client->probe($branchId);
        $this->repo->queueSync(
            $branchId,
            'connection',
            'manual.test_connection',
            $result['success'] ? 'success' : 'failed',
            'branch:' . $branchId,
            $result['request'] ?? [],
            $result['response'] ?? ['message' => (string)($result['message'] ?? '')],
            'outbound',
            null,
            'connection:' . $branchId,
            1,
            isset($result['status']) ? (int)$result['status'] : null,
            null,
            $result['success'] ? null : (string)($result['message'] ?? '')
        );
        return $result;
    }

    public function processPendingQueue(int $branchId, int $limit = 10): array
    {
        $logs = $this->repo->getRetryableLogs($branchId, ['pending', 'retry_scheduled', 'config_missing'], $limit);
        return $this->processLogs($logs);
    }

    public function retryFailedQueue(int $branchId, int $limit = 10): array
    {
        $logs = $this->repo->getRetryableLogs($branchId, ['failed', 'retry_scheduled', 'config_missing'], $limit);
        return $this->processLogs($logs);
    }

    public function processAllActiveBranches(int $limitPerBranch = 20): array
    {
        $processed = 0;
        $success = 0;
        $failed = 0;

        foreach ($this->repo->getActiveBranchIds() as $branchId) {
            $result = $this->processPendingQueue($branchId, $limitPerBranch);
            $processed += (int)($result['processed'] ?? 0);
            $success += (int)($result['success_count'] ?? 0);
            $failed += (int)($result['failed_count'] ?? 0);
        }

        return [
            'success' => true,
            'processed' => $processed,
            'success_count' => $success,
            'failed_count' => $failed,
            'message' => sprintf('Runner Moka memproses %d queue: %d berhasil, %d gagal.', $processed, $success, $failed),
        ];
    }

    public function resendOrder(int $branchId, int $orderId): array
    {
        if (!$this->isEnabled($branchId)) {
            return ['success' => false, 'message' => 'Plugin Moka belum aktif untuk cabang ini.'];
        }

        $order = (new OrderModel())->getWithItems($orderId);
        if (!$order || (int)($order['branch_id'] ?? 0) !== $branchId) {
            return ['success' => false, 'message' => 'Order tidak ditemukan untuk cabang ini.'];
        }

        $this->queueOrderSync($order, 'manual.order_resend');

        return [
            'success' => true,
            'message' => 'Order dimasukkan ulang ke queue Moka dan akan dikirim sesuai mode live cabang.',
        ];
    }

    public function pushQueuedLog(int $logId): array
    {
        $log = $this->repo->findLogById($logId);
        if (!$log) {
            return ['success' => false, 'message' => 'Log queue tidak ditemukan.'];
        }

        $branchId = (int)($log['branch_id'] ?? 0);
        if (!$this->isEnabled($branchId)) {
            return ['success' => false, 'message' => 'Plugin Moka tidak aktif untuk cabang queue ini.'];
        }

        if ((string)($log['entity_type'] ?? '') !== 'order') {
            return ['success' => false, 'message' => 'Hanya queue order yang bisa dikirim ulang.'];
        }

        $orderId = (int)($log['order_id'] ?? 0);
        if ($orderId <= 0) {
            return ['success' => false, 'message' => 'Queue order tidak memiliki order_id valid.'];
        }

        $order = (new OrderModel())->getWithItems($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Data order tidak ditemukan.'];
        }

        $payload = $this->buildOrderPayload($branchId, $order);
        $result = $this->client->sendOrderUpsert($branchId, $payload);
        $attemptCount = max(0, (int)($log['attempt_count'] ?? 0)) + 1;
        $config = $this->client->getBranchConfig($branchId);

        if ($result['success']) {
            $externalRef = $this->extractExternalRef($result['data']);
            $this->repo->updateLogResult(
                $logId,
                'success',
                [
                    'request' => $result['request_preview'],
                    'response' => $result['response_preview'],
                ],
                $result['status'],
                $externalRef,
                null,
                null,
                $attemptCount
            );
            $this->repo->upsertOrderSyncStatus(
                $branchId,
                $orderId,
                (string)($order['order_number'] ?? ''),
                [
                    'last_status' => 'success',
                    'last_event_name' => (string)($log['event_name'] ?? 'order.sync'),
                    'last_attempt_count' => $attemptCount,
                    'last_http_status' => $result['status'],
                    'external_ref' => $externalRef,
                    'last_error' => null,
                    'last_log_id' => $logId,
                    'last_queued_at' => date('Y-m-d H:i:s'),
                    'last_synced_at' => date('Y-m-d H:i:s'),
                ]
            );

            return [
                'success' => true,
                'message' => 'Order berhasil dikirim ke Moka.',
            ];
        }

        $canRetry = $attemptCount < max(1, $config['max_retries']);
        $nextRetryAt = $canRetry
            ? date('Y-m-d H:i:s', time() + max(30, $config['retry_delay_seconds']))
            : null;
        $newStatus = $canRetry ? 'retry_scheduled' : 'failed';

        $this->repo->updateLogResult(
            $logId,
            $newStatus,
            [
                'request' => $result['request_preview'],
                'response' => $result['response_preview'],
            ],
            $result['status'],
            null,
            $result['error'] !== '' ? $result['error'] : 'Unknown Moka sync failure.',
            $nextRetryAt,
            $attemptCount
        );
        $this->repo->upsertOrderSyncStatus(
            $branchId,
            $orderId,
            (string)($order['order_number'] ?? ''),
            [
                'last_status' => $newStatus,
                'last_event_name' => (string)($log['event_name'] ?? 'order.sync'),
                'last_attempt_count' => $attemptCount,
                'last_http_status' => $result['status'],
                'external_ref' => null,
                'last_error' => $result['error'] !== '' ? $result['error'] : 'Unknown Moka sync failure.',
                'last_log_id' => $logId,
                'last_queued_at' => date('Y-m-d H:i:s'),
                'last_synced_at' => null,
            ]
        );

        return [
            'success' => false,
            'message' => $newStatus === 'retry_scheduled'
                ? 'Pengiriman order gagal sementara dan dijadwalkan retry.'
                : 'Pengiriman order gagal dan butuh resend manual.',
        ];
    }

    public function handleInboundWebhook(int $branchId, array|string|null $payload, string $sourceType = 'webhook'): array
    {
        if (!$this->isEnabled($branchId)) {
            return ['success' => false, 'message' => 'Plugin Moka tidak aktif untuk cabang ini.'];
        }

        $payloadArray = is_array($payload)
            ? $payload
            : (is_string($payload) && $payload !== '' ? ['raw' => $payload] : []);
        $resolved = $this->resolveInboundWebhookData($branchId, $payloadArray);

        $this->repo->queueSync(
            $branchId,
            'webhook',
            $sourceType === 'simulation' ? 'webhook.simulation' : 'webhook.sync_attempt',
            $resolved['order'] ? 'success' : 'failed',
            $resolved['order_number'] ?: ('branch:' . $branchId),
            [
                'mapping' => $this->getMappingPreview($branchId)['inbound'],
                'payload' => $payloadArray,
            ],
            [
                'resolved' => [
                    'order_number' => $resolved['order_number'],
                    'external_ref' => $resolved['external_ref'],
                    'order_status' => $resolved['internal_order_status'],
                    'payment_status' => $resolved['internal_payment_status'],
                ],
                'message' => $resolved['order']
                    ? 'Inbound webhook Moka berhasil dipetakan.'
                    : 'Order internal tidak ditemukan dari payload webhook.',
            ],
            'inbound',
            (int)($resolved['order']['id'] ?? 0),
            'webhook:' . $branchId . ':' . ($resolved['order_number'] ?: uniqid('', true))
        );

        if (!$resolved['order']) {
            $this->repo->addWebhookAudit(
                $branchId,
                null,
                $resolved['order_number'],
                $sourceType,
                $resolved['remote_order_status'],
                $resolved['remote_payment_status'],
                null,
                null,
                null,
                null,
                ['payload' => $payloadArray],
                [],
                'Order internal tidak ditemukan dari payload inbound Moka.'
            );
            return ['success' => false, 'message' => 'Payload webhook diterima tetapi order internal belum bisa dipetakan.'];
        }

        /** @var array<string,mixed> $order */
        $order = $resolved['order'];
        $orderId = (int)($order['id'] ?? 0);
        $orderModel = new OrderModel();
        $oldOrderStatus = (string)($order['order_status'] ?? '');
        $oldPaymentStatus = (string)($order['payment_status'] ?? '');
        $changedFields = [];

        self::$suppressedOrderIds[$orderId] = true;
        try {
            if ($resolved['internal_order_status'] !== null && $resolved['internal_order_status'] !== (string)($order['order_status'] ?? '')) {
                $orderModel->updateStatus($orderId, $resolved['internal_order_status']);
                $order['order_status'] = $resolved['internal_order_status'];
                $changedFields[] = 'order_status';
            }
            if ($resolved['internal_payment_status'] !== null && $resolved['internal_payment_status'] !== (string)($order['payment_status'] ?? '')) {
                $orderModel->updatePayment($orderId, $resolved['internal_payment_status']);
                $order['payment_status'] = $resolved['internal_payment_status'];
                $changedFields[] = 'payment_status';
            }
        } finally {
            unset(self::$suppressedOrderIds[$orderId]);
        }

        $note = $changedFields === []
            ? 'Inbound Moka berhasil dipetakan, tetapi tidak ada perubahan status internal.'
            : 'Inbound Moka memperbarui field: ' . implode(', ', $changedFields) . '.';
        $this->repo->addWebhookAudit(
            $branchId,
            $orderId,
            (string)($order['order_number'] ?? ''),
            $sourceType,
            $resolved['remote_order_status'],
            $resolved['remote_payment_status'],
            $oldOrderStatus !== '' ? $oldOrderStatus : null,
            (string)($order['order_status'] ?? ''),
            $oldPaymentStatus !== '' ? $oldPaymentStatus : null,
            (string)($order['payment_status'] ?? ''),
            ['payload' => $payloadArray],
            $changedFields,
            $note
        );

        $currentSync = $this->repo->getOrderSyncStatus($orderId);
        $this->repo->upsertOrderSyncStatus(
            $branchId,
            $orderId,
            (string)($order['order_number'] ?? ''),
            [
                'last_status' => 'success',
                'last_event_name' => 'webhook.sync',
                'last_attempt_count' => (int)($currentSync['last_attempt_count'] ?? 0),
                'last_http_status' => 200,
                'external_ref' => $resolved['external_ref'],
                'last_error' => null,
                'last_log_id' => isset($currentSync['last_log_id']) ? (int)$currentSync['last_log_id'] : null,
                'last_queued_at' => date('Y-m-d H:i:s'),
                'last_synced_at' => date('Y-m-d H:i:s'),
            ]
        );

        return [
            'success' => true,
            'message' => $sourceType === 'simulation'
                ? 'Simulasi webhook Moka selesai diproses.'
                : 'Webhook Moka berhasil menyinkronkan status order internal.',
            'data' => [
                'order_id' => $orderId,
                'order_number' => (string)($order['order_number'] ?? ''),
                'order_status' => $resolved['internal_order_status'],
                'payment_status' => $resolved['internal_payment_status'],
            ],
        ];
    }

    public function getConnectionStatus(int $branchId): array
    {
        $config = $this->client->getBranchConfig($branchId);
        return [
            'enabled' => $this->isEnabled($branchId),
            'base_url' => $config['base_url'],
            'auth_mode' => $config['auth_mode'],
            'merchant_id' => $config['merchant_id'],
            'outlet_id' => $config['outlet_id'],
            'sync_orders' => $this->repo->getBranchSetting($branchId, 'sync_orders', '1') === '1',
            'sync_products' => $this->repo->getBranchSetting($branchId, 'sync_products', '1') === '1',
            'sync_customers' => $this->repo->getBranchSetting($branchId, 'sync_customers', '1') === '1',
            'sync_outlets' => $this->repo->getBranchSetting($branchId, 'sync_outlets', '1') === '1',
            'has_credentials' => $config['has_credentials'],
            'timeout_seconds' => $config['timeout_seconds'],
            'verify_ssl' => $config['verify_ssl'],
            'mode' => $this->repo->getGlobalSetting('connection_mode', 'sandbox'),
            'live_order_push' => $config['live_order_push'],
            'live_catalog_pull' => $config['live_catalog_pull'],
            'max_retries' => $config['max_retries'],
            'retry_delay_seconds' => $config['retry_delay_seconds'],
        ];
    }

    public function getRepository(): MokaConnectRepository
    {
        return $this->repo;
    }

    public function getMappingPreview(int $branchId): array
    {
        return [
            'order' => [
                'Top-level outbound order key: ' . $this->repo->getBranchSetting($branchId, 'map_out_order_key', 'order'),
                'order_number -> ' . $this->repo->getBranchSetting($branchId, 'map_out_order_id_key', 'external_order_id'),
                'receipt -> ' . $this->repo->getBranchSetting($branchId, 'map_out_receipt_key', 'receipt_number'),
                'line items key -> ' . $this->repo->getBranchSetting($branchId, 'map_out_line_items_key', 'line_items'),
                'totals key -> ' . $this->repo->getBranchSetting($branchId, 'map_out_totals_key', 'totals'),
                'customer key -> ' . $this->repo->getBranchSetting($branchId, 'map_out_customer_key', 'customer'),
                'payment key -> ' . $this->repo->getBranchSetting($branchId, 'map_out_payment_key', 'payment'),
                'metadata key -> ' . $this->repo->getBranchSetting($branchId, 'map_out_metadata_key', 'metadata'),
            ],
            'catalog' => [
                'menu_items.id -> products[].external_product_id',
                'menu_items.name -> products[].name',
                'category_name / category_slug -> products[].category.*',
                'effective_price -> products[].pricing.price',
                'effective_available -> products[].availability.is_active',
                'variants[] -> products[].variants[]',
                'toppings[] -> products[].modifiers[]',
            ],
            'inbound' => [
                'order number path: ' . $this->repo->getBranchSetting($branchId, 'map_in_order_number_path', 'order.external_order_id|order.receipt_number|external_order_id|receipt_number|order_number'),
                'external ref path: ' . $this->repo->getBranchSetting($branchId, 'map_in_external_ref_path', 'id|order.id|external_id'),
                'order status path: ' . $this->repo->getBranchSetting($branchId, 'map_in_order_status_path', 'order.status|status|order_status'),
                'payment status path: ' . $this->repo->getBranchSetting($branchId, 'map_in_payment_status_path', 'order.payment.status|payment.status|payment_status'),
            ],
        ];
    }

    private function hasConnectionConfig(int $branchId): bool
    {
        return $this->client->getBranchConfig($branchId)['has_credentials'];
    }

    private function buildOrderPayload(int $branchId, array $order): array
    {
        $fulfillment = (string)($order['fulfillment_type'] ?? 'delivery');
        $paymentStatus = (string)($order['payment_status'] ?? 'unpaid');
        $topOrderKey = $this->repo->getBranchSetting($branchId, 'map_out_order_key', 'order');
        $customerKey = $this->repo->getBranchSetting($branchId, 'map_out_customer_key', 'customer');
        $paymentKey = $this->repo->getBranchSetting($branchId, 'map_out_payment_key', 'payment');
        $totalsKey = $this->repo->getBranchSetting($branchId, 'map_out_totals_key', 'totals');
        $lineItemsKey = $this->repo->getBranchSetting($branchId, 'map_out_line_items_key', 'line_items');
        $metadataKey = $this->repo->getBranchSetting($branchId, 'map_out_metadata_key', 'metadata');
        $orderIdKey = $this->repo->getBranchSetting($branchId, 'map_out_order_id_key', 'external_order_id');
        $receiptKey = $this->repo->getBranchSetting($branchId, 'map_out_receipt_key', 'receipt_number');
        $statusKey = $this->repo->getBranchSetting($branchId, 'map_out_status_key', 'status');
        $fulfillmentKey = $this->repo->getBranchSetting($branchId, 'map_out_fulfillment_key', 'fulfillment');

        $lineItems = [];
        foreach ((array)($order['items'] ?? []) as $item) {
            $lineItems[] = [
                'external_line_id' => 'line-' . (string)($item['id'] ?? uniqid('', true)),
                'product_id' => (int)($item['menu_item_id'] ?? 0),
                'name' => (string)($item['menu_name'] ?? ''),
                'variant' => [
                    'id' => isset($item['variant_id']) ? (int)$item['variant_id'] : null,
                    'label' => (string)($item['variant_label'] ?? ''),
                ],
                'quantity' => (int)($item['quantity'] ?? 0),
                'pricing' => [
                    'unit_price' => (float)($item['unit_price'] ?? 0),
                    'subtotal' => (float)($item['subtotal'] ?? 0),
                ],
                'notes' => (string)($item['notes'] ?? ''),
            ];
        }

        $payload = [
            $orderIdKey => (string)($order['order_number'] ?? ''),
            $receiptKey => (string)($order['order_number'] ?? ''),
            'source' => 'kopibot',
            'channel' => (string)($order['channel'] ?? 'web'),
            $statusKey => $this->mapOrderStatus((string)($order['order_status'] ?? 'pending')),
            $customerKey => [
                'external_customer_id' => (int)($order['customer_id'] ?? 0),
                'name' => (string)($order['customer_name'] ?? ''),
                'email' => (string)($order['customer_email'] ?? ''),
                'phone' => (string)($order['customer_wa'] ?? ''),
            ],
            $fulfillmentKey => [
                'type' => $this->mapFulfillmentType($fulfillment),
                'table_number' => (string)($order['table_number'] ?? ''),
                'delivery_address' => (string)($order['delivery_address'] ?? ''),
                'postal_code' => (string)($order['postal_code'] ?? ''),
            ],
            $paymentKey => [
                'status' => $this->mapPaymentStatus($paymentStatus),
                'paid_at' => (string)($order['paid_at'] ?? ''),
            ],
            $totalsKey => [
                'subtotal' => (float)($order['subtotal'] ?? 0),
                'discount_total' => (float)($order['discount_amount'] ?? 0),
                'tax_rate' => (float)($order['ppn_rate'] ?? 0),
                'tax_total' => (float)($order['ppn_amount'] ?? 0),
                'grand_total' => (float)($order['total_amount'] ?? 0),
            ],
            $lineItemsKey => $lineItems,
            $metadataKey => [
                'promo_code' => (string)($order['promo_code'] ?? ''),
                'customer_notes' => (string)($order['notes'] ?? ''),
                'created_at' => (string)($order['created_at'] ?? ''),
                'branch_id' => (int)($order['branch_id'] ?? 0),
                'internal_order_id' => (int)($order['id'] ?? 0),
            ],
        ];

        return [$topOrderKey => $payload];
    }

    private function buildCatalogItemPayload(array $item): array
    {
        return [
            'external_product_id' => (int)($item['id'] ?? 0),
            'name' => (string)($item['name'] ?? ''),
            'description' => (string)($item['description'] ?? ''),
            'category' => [
                'id' => (int)($item['category_id'] ?? 0),
                'name' => (string)($item['category_name'] ?? ''),
                'slug' => (string)($item['category_slug'] ?? ''),
            ],
            'pricing' => [
                'price' => (float)($item['effective_price'] ?? $item['price'] ?? 0),
            ],
            'availability' => [
                'is_active' => (int)($item['effective_available'] ?? 0) === 1,
            ],
            'variants' => array_map(static function (array $variant): array {
                return [
                    'id' => (int)($variant['id'] ?? 0),
                    'label' => (string)($variant['label'] ?? ''),
                    'price' => (float)($variant['effective_price'] ?? 0),
                ];
            }, (array)($item['variants'] ?? [])),
            'modifiers' => array_map(static function (array $topping): array {
                return [
                    'id' => (int)($topping['id'] ?? 0),
                    'name' => (string)($topping['name'] ?? ''),
                    'price_delta' => (float)($topping['price_delta'] ?? 0),
                ];
            }, (array)($item['toppings'] ?? [])),
        ];
    }

    private function summarizeCatalogResponse(array $data): array
    {
        $candidateKeys = ['data', 'products', 'items', 'catalog'];
        $count = 0;
        foreach ($candidateKeys as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $count = count($data[$key]);
                break;
            }
        }

        if ($count === 0 && array_is_list($data)) {
            $count = count($data);
        }

        return [
            'item_count' => $count,
            'summary_text' => $count > 0
                ? ($count . ' item katalog terdeteksi dari response Moka.')
                : 'Response diterima, tetapi jumlah item katalog belum bisa diidentifikasi otomatis.',
        ];
    }

    private function processLogs(array $logs): array
    {
        $success = 0;
        $failed = 0;

        foreach ($logs as $log) {
            $result = $this->pushQueuedLog((int)($log['id'] ?? 0));
            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => true,
            'processed' => count($logs),
            'success_count' => $success,
            'failed_count' => $failed,
            'message' => count($logs) > 0
                ? sprintf('Queue diproses: %d berhasil, %d gagal.', $success, $failed)
                : 'Tidak ada queue order yang siap diproses.',
        ];
    }

    private function enrichOrder(array $order): array
    {
        if (!empty($order['items']) || empty($order['id'])) {
            return $order;
        }

        $fullOrder = (new OrderModel())->getWithItems((int)$order['id']);
        return $fullOrder ?: $order;
    }

    private function mapOrderStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'completed' => 'closed',
            'cancelled', 'canceled' => 'cancelled',
            'confirmed', 'preparing', 'processing' => 'open',
            default => 'pending',
        };
    }

    private function mapPaymentStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'paid', 'settled' => 'paid',
            'failed', 'expired' => 'failed',
            default => 'unpaid',
        };
    }

    private function mapFulfillmentType(string $type): string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'pickup' => 'pickup',
            'table' => 'dine_in',
            default => 'delivery',
        };
    }

    private function extractExternalRef(mixed $data): ?string
    {
        if (!is_array($data)) {
            return null;
        }

        foreach (['id', 'order_id', 'external_id', 'receipt_number'] as $key) {
            if (!empty($data[$key])) {
                return (string)$data[$key];
            }
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return $this->extractExternalRef($data['data']);
        }

        return null;
    }

    private function resolveInboundWebhookData(int $branchId, array $payload): array
    {
        $orderNumberPath = $this->repo->getBranchSetting($branchId, 'map_in_order_number_path', 'order.external_order_id|order.receipt_number|external_order_id|receipt_number|order_number');
        $externalRefPath = $this->repo->getBranchSetting($branchId, 'map_in_external_ref_path', 'id|order.id|external_id');
        $orderStatusPath = $this->repo->getBranchSetting($branchId, 'map_in_order_status_path', 'order.status|status|order_status');
        $paymentStatusPath = $this->repo->getBranchSetting($branchId, 'map_in_payment_status_path', 'order.payment.status|payment.status|payment_status');

        $orderNumber = $this->extractByPathCandidates($payload, $orderNumberPath);
        $externalRef = $this->extractByPathCandidates($payload, $externalRefPath);
        $remoteOrderStatus = $this->extractByPathCandidates($payload, $orderStatusPath);
        $remotePaymentStatus = $this->extractByPathCandidates($payload, $paymentStatusPath);

        $orderModel = new OrderModel();
        $order = $orderNumber !== null && $orderNumber !== ''
            ? $orderModel->findByOrderNumber($orderNumber)
            : false;

        if (!$order && $externalRef !== null && $externalRef !== '') {
            $syncStatus = $this->repo->getOrderSyncStatusByExternalRef($branchId, $externalRef);
            if ($syncStatus) {
                $order = $orderModel->getWithItems((int)($syncStatus['order_id'] ?? 0));
                $orderNumber = (string)($syncStatus['order_number'] ?? $orderNumber);
            }
        }

        return [
            'order' => $order ?: null,
            'order_number' => $orderNumber,
            'external_ref' => $externalRef,
            'remote_order_status' => $remoteOrderStatus,
            'remote_payment_status' => $remotePaymentStatus,
            'internal_order_status' => $remoteOrderStatus !== null ? $this->mapInboundStatus($branchId, $remoteOrderStatus, 'order') : null,
            'internal_payment_status' => $remotePaymentStatus !== null ? $this->mapInboundStatus($branchId, $remotePaymentStatus, 'payment') : null,
        ];
    }

    private function extractByPathCandidates(array $payload, string $rawCandidates): ?string
    {
        $candidates = array_filter(array_map('trim', explode('|', $rawCandidates)));
        foreach ($candidates as $path) {
            $value = $this->extractValueByPath($payload, $path);
            if ($value === null) {
                continue;
            }
            $string = trim((string)$value);
            if ($string !== '') {
                return $string;
            }
        }

        return null;
    }

    private function extractValueByPath(array $payload, string $path): mixed
    {
        $segments = array_values(array_filter(explode('.', trim($path)), static fn(string $part): bool => $part !== ''));
        if ($segments === []) {
            return null;
        }

        $cursor = $payload;
        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    private function mapInboundStatus(int $branchId, string $remoteStatus, string $type): string
    {
        $remoteStatus = strtolower(trim($remoteStatus));
        $mapRaw = $type === 'payment'
            ? $this->repo->getBranchSetting($branchId, 'map_payment_status_pairs', "paid=paid\nunpaid=unpaid\npending=unpaid\nfailed=failed\nexpired=failed")
            : $this->repo->getBranchSetting($branchId, 'map_order_status_pairs', "pending=pending\nopen=confirmed\nprocessing=preparing\nclosed=completed\ncancelled=cancelled");

        foreach (preg_split("/\r\n|\n|\r/", $mapRaw) as $line) {
            $line = trim((string)$line);
            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }
            [$from, $to] = array_map(static fn(string $value): string => strtolower(trim($value)), explode('=', $line, 2));
            if ($from === $remoteStatus && $to !== '') {
                return $to;
            }
        }

        return $type === 'payment' ? 'unpaid' : 'pending';
    }
}
