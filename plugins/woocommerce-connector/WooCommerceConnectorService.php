<?php

declare(strict_types=1);

require_once __DIR__ . '/WooCommerceConnectorClient.php';

use App\Models\MenuModel;
use App\Models\OrderModel;

final class WooCommerceConnectorService
{
    private WooCommerceConnectorRepository $repo;
    private WooCommerceConnectorClient $client;
    private static array $suppressedOrderIds = [];

    public function __construct(?WooCommerceConnectorRepository $repo = null)
    {
        $this->repo = $repo ?? new WooCommerceConnectorRepository();
        $this->client = new WooCommerceConnectorClient($this->repo);
    }

    public function isEnabled(int $branchId): bool
    {
        return $branchId > 0 && $this->repo->getBranchSetting($branchId, 'is_active', '0') === '1';
    }

    public function queueOrderSync(array $order, string $eventName): void
    {
        $branchId = (int)($order['branch_id'] ?? 0);
        $orderId = (int)($order['id'] ?? 0);
        if (!$this->isEnabled($branchId) || ($orderId > 0 && isset(self::$suppressedOrderIds[$orderId]))) {
            return;
        }

        $config = $this->client->getBranchConfig($branchId);
        if ($config['live_order_push'] && $this->hasConnectionConfig($branchId)) {
            $this->pushOrderLive($order, $eventName);
            return;
        }

        $payload = $this->buildOrderPayload($order);
        $status = $this->hasConnectionConfig($branchId) ? 'pending' : 'config_missing';
        $message = $status === 'pending'
            ? 'Order queued for WooCommerce sync.'
            : 'WooCommerce credentials are incomplete.';

        $this->repo->logSync(
            $branchId,
            'order',
            $eventName,
            $status,
            (string)($order['order_number'] ?? $order['id'] ?? ''),
            $payload,
            ['message' => $message]
        );
    }

    public function probe(int $branchId): array
    {
        return $this->client->probe($branchId);
    }

    public function syncProductsSnapshot(int $branchId): void
    {
        if (!$this->isEnabled($branchId)) {
            return;
        }

        $menuModel = new MenuModel();
        $items = $menuModel->getMenuForBranch($branchId);
        $status = $this->hasConnectionConfig($branchId) ? 'pending' : 'config_missing';

        $this->repo->logSync(
            $branchId,
            'product',
            'manual.catalog_sync',
            $status,
            'branch:' . $branchId,
            [
                'branch_id' => $branchId,
                'item_count' => count($items),
                'sample_items' => array_slice(array_map(static function (array $item): array {
                    return [
                        'id' => (int)($item['id'] ?? 0),
                        'name' => (string)($item['name'] ?? ''),
                        'price' => (float)($item['effective_price'] ?? $item['price'] ?? 0),
                        'available' => (int)($item['effective_available'] ?? 0),
                        'category_id' => (int)($item['category_id'] ?? 0),
                    ];
                }, $items), 0, 5),
            ],
            ['message' => 'WooCommerce catalog snapshot prepared.']
        );
    }

    public function pullProductsLive(int $branchId): array
    {
        if (!$this->isEnabled($branchId)) {
            return ['success' => false, 'message' => 'Plugin WooCommerce tidak aktif untuk cabang ini.'];
        }
        if (!$this->hasConnectionConfig($branchId)) {
            return ['success' => false, 'message' => 'Credential WooCommerce belum lengkap.'];
        }

        $config = $this->client->getBranchConfig($branchId);
        if (!$config['live_catalog_pull']) {
            return ['success' => false, 'message' => 'Live catalog pull sedang nonaktif untuk cabang ini.'];
        }

        $categoryMap = [];
        $categoryPage = 1;
        do {
            $categoryResult = $this->client->pullCategories($branchId, $categoryPage, 100);
            if (!$categoryResult['success']) {
                $this->repo->logSync(
                    $branchId,
                    'product',
                    'live.catalog_pull',
                    'failed',
                    'branch:' . $branchId,
                    ['page' => $categoryPage],
                    $categoryResult['response_preview'] ?? ['error' => $categoryResult['error'] ?? 'Unknown category pull error.']
                );
                return ['success' => false, 'message' => 'Gagal menarik kategori WooCommerce.'];
            }

            $rows = is_array($categoryResult['data']) ? $categoryResult['data'] : [];
            foreach ($rows as $index => $category) {
                if (!is_array($category) || empty($category['name'])) {
                    continue;
                }
                $categoryMap[(int)($category['id'] ?? 0)] = $this->repo->upsertCategory(
                    (string)$category['name'],
                    (string)($category['slug'] ?? ''),
                    $index
                );
            }

            $categoryPage++;
        } while (!empty($rows));

        $page = 1;
        $summary = [
            'pulled_products' => 0,
            'inserted_items' => 0,
            'updated_items' => 0,
            'inserted_variants' => 0,
            'updated_variants' => 0,
        ];

        do {
            $result = $this->client->pullProducts($branchId, $page, $config['per_page']);
            if (!$result['success']) {
                $this->repo->logSync(
                    $branchId,
                    'product',
                    'live.catalog_pull',
                    'failed',
                    'branch:' . $branchId,
                    ['page' => $page],
                    $result['response_preview'] ?? ['error' => $result['error'] ?? 'Unknown product pull error.']
                );
                return ['success' => false, 'message' => 'Gagal menarik produk WooCommerce.'];
            }

            $products = is_array($result['data']) ? $result['data'] : [];
            foreach ($products as $sort => $product) {
                if (!is_array($product) || empty($product['name'])) {
                    continue;
                }
                $summary['pulled_products']++;
                $this->importWooProduct($branchId, $product, $categoryMap, $summary, $sort);
            }
            $page++;
        } while (!empty($products));

        $this->repo->logSync(
            $branchId,
            'product',
            'live.catalog_pull',
            'success',
            'branch:' . $branchId,
            ['summary' => $summary],
            ['message' => 'WooCommerce catalog pull completed.']
        );

        return ['success' => true, 'message' => 'Katalog WooCommerce berhasil ditarik ke branch ini.', 'data' => $summary];
    }

    public function syncRecentOrdersSnapshot(int $branchId, int $limit = 20): void
    {
        if (!$this->isEnabled($branchId)) {
            return;
        }

        $orderModel = new OrderModel();
        $orders = $orderModel->getByBranch($branchId, max(1, min(100, $limit)), 0);
        $status = $this->hasConnectionConfig($branchId) ? 'pending' : 'config_missing';

        $this->repo->logSync(
            $branchId,
            'order',
            'manual.order_sync',
            $status,
            'branch:' . $branchId,
            [
                'branch_id' => $branchId,
                'order_count' => count($orders),
                'sample_orders' => array_slice(array_map(static function (array $order): array {
                    return [
                        'id' => (int)($order['id'] ?? 0),
                        'order_number' => (string)($order['order_number'] ?? ''),
                        'total_amount' => (float)($order['total_amount'] ?? 0),
                        'order_status' => (string)($order['order_status'] ?? ''),
                        'payment_status' => (string)($order['payment_status'] ?? ''),
                    ];
                }, $orders), 0, 5),
            ],
            ['message' => 'Recent orders snapshot prepared for WooCommerce.']
        );
    }

    public function pushRecentOrdersLive(int $branchId, int $limit = 10): array
    {
        if (!$this->isEnabled($branchId)) {
            return ['success' => false, 'message' => 'Plugin WooCommerce tidak aktif untuk cabang ini.'];
        }
        if (!$this->hasConnectionConfig($branchId)) {
            return ['success' => false, 'message' => 'Credential WooCommerce belum lengkap.'];
        }

        $orderModel = new OrderModel();
        $rows = $orderModel->getByBranch($branchId, max(1, min(50, $limit)), 0);
        $success = 0;
        $failed = 0;
        $messages = [];
        foreach ($rows as $row) {
            $order = $orderModel->getWithItems((int)($row['id'] ?? 0));
            if (!$order) {
                continue;
            }
            $result = $this->pushOrderLive($order, 'manual.order_push');
            if ($result['success']) {
                $success++;
            } else {
                $failed++;
                $messages[] = (string)($result['message'] ?? 'Unknown error');
            }
        }

        return [
            'success' => $failed === 0,
            'message' => 'Push recent orders selesai. Success: ' . $success . ', Failed: ' . $failed . '.',
            'data' => [
                'success_count' => $success,
                'failed_count' => $failed,
                'errors' => $messages,
            ],
        ];
    }

    public function handleInboundWebhook(int $branchId, array|string|null $payload): array
    {
        if (!$this->isEnabled($branchId)) {
            return ['success' => false, 'message' => 'Plugin WooCommerce tidak aktif untuk cabang ini.'];
        }

        $payloadArray = is_array($payload)
            ? $payload
            : (is_string($payload) && $payload !== '' ? ['raw' => $payload] : []);

        $orderNumber = $this->resolveOrderNumber($payloadArray);
        if ($orderNumber === '') {
            $this->repo->logSync(
                $branchId,
                'webhook',
                'webhook.status_sync',
                'failed',
                'branch:' . $branchId,
                ['payload' => $payloadArray],
                ['message' => 'Order number tidak ditemukan di payload WooCommerce.'],
                'inbound'
            );

            return ['success' => false, 'message' => 'Payload webhook diterima tetapi order_number belum bisa dipetakan.'];
        }

        $order = $this->repo->findOrderByNumber($branchId, $orderNumber);
        if (!$order) {
            $this->repo->logSync(
                $branchId,
                'webhook',
                'webhook.status_sync',
                'failed',
                $orderNumber,
                ['payload' => $payloadArray],
                ['message' => 'Order internal tidak ditemukan untuk order_number tersebut.'],
                'inbound'
            );

            return ['success' => false, 'message' => 'Order internal tidak ditemukan dari payload WooCommerce.'];
        }

        $remoteOrderStatus = $this->resolveRemoteOrderStatus($payloadArray);
        $remotePaymentStatus = $this->resolveRemotePaymentStatus($payloadArray);
        $statusMap = $this->parseMap(
            $this->repo->getBranchSetting($branchId, 'order_status_map', 'processing:processing,completed:completed,cancelled:cancelled,on-hold:pending,pending:pending')
        );
        $paymentMap = $this->parseMap(
            $this->repo->getBranchSetting($branchId, 'payment_status_map', 'paid:paid,pending:pending,failed:failed,refunded:refunded')
        );

        $internalOrderStatus = $remoteOrderStatus !== '' ? ($statusMap[$remoteOrderStatus] ?? null) : null;
        $internalPaymentStatus = $remotePaymentStatus !== '' ? ($paymentMap[$remotePaymentStatus] ?? null) : null;

        $orderModel = new OrderModel();
        $changed = [];
        $orderId = (int)($order['id'] ?? 0);
        self::$suppressedOrderIds[$orderId] = true;
        try {
            if ($internalOrderStatus !== null && $internalOrderStatus !== (string)($order['order_status'] ?? '')) {
                $orderModel->updateStatus($orderId, $internalOrderStatus);
                $changed['order_status'] = $internalOrderStatus;
            }
            if ($internalPaymentStatus !== null && $internalPaymentStatus !== (string)($order['payment_status'] ?? '')) {
                $orderModel->updatePayment($orderId, $internalPaymentStatus);
                $changed['payment_status'] = $internalPaymentStatus;
            }
        } finally {
            unset(self::$suppressedOrderIds[$orderId]);
        }

        $this->repo->logSync(
            $branchId,
            'webhook',
            'webhook.status_sync',
            'success',
            $orderNumber,
            ['payload' => $payloadArray],
            [
                'remote_order_status' => $remoteOrderStatus,
                'remote_payment_status' => $remotePaymentStatus,
                'changed_fields' => $changed,
                'message' => $changed === []
                    ? 'Webhook WooCommerce mapped successfully with no status changes.'
                    : 'Webhook WooCommerce updated internal order state.',
            ],
            'inbound'
        );

        return [
            'success' => true,
            'message' => $changed === []
                ? 'Webhook WooCommerce diterima, tetapi tidak ada perubahan status internal.'
                : 'Webhook WooCommerce berhasil memperbarui status order internal.',
            'data' => [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'order_status' => $internalOrderStatus,
                'payment_status' => $internalPaymentStatus,
            ],
        ];
    }

    public function getConnectionStatus(int $branchId): array
    {
        $config = $this->client->getBranchConfig($branchId);
        return [
            'enabled' => $this->isEnabled($branchId),
            'base_url' => $config['base_url'],
            'store_url' => $config['store_url'],
            'has_credentials' => $config['has_credentials'],
            'sync_orders' => $this->repo->getBranchSetting($branchId, 'sync_orders', '1') === '1',
            'sync_products' => $this->repo->getBranchSetting($branchId, 'sync_products', '1') === '1',
            'live_order_push' => $config['live_order_push'],
            'live_catalog_pull' => $config['live_catalog_pull'],
            'timeout_seconds' => $config['timeout_seconds'],
            'mode' => $config['mode'],
        ];
    }

    public function getRepository(): WooCommerceConnectorRepository
    {
        return $this->repo;
    }

    private function hasConnectionConfig(int $branchId): bool
    {
        $config = $this->client->getBranchConfig($branchId);
        return $config['has_credentials'];
    }

    private function importWooProduct(int $branchId, array $product, array $categoryMap, array &$summary, int $sort): void
    {
        $remoteCategoryId = (int)($product['categories'][0]['id'] ?? 0);
        $localCategoryId = $categoryMap[$remoteCategoryId] ?? $this->repo->upsertCategory(
            (string)($product['categories'][0]['name'] ?? 'WooCommerce'),
            (string)($product['categories'][0]['slug'] ?? ''),
            $sort
        );

        $productName = (string)$product['name'];
        $productSlug = trim((string)($product['slug'] ?? ''));
        if ($productSlug === '') {
            $productSlug = $this->slugify($productName . '-' . (string)($product['id'] ?? 'woo'));
        }

        $basePrice = $this->resolveWooProductPrice($product);
        $availability = $this->resolveWooAvailability($product);
        $menuItemData = [
            'category_id' => $localCategoryId,
            'name' => $productName,
            'slug' => $productSlug,
            'description' => $this->cleanDescription((string)(($product['short_description'] ?? '') !== '' ? $product['short_description'] : ($product['description'] ?? ''))),
            'price' => $basePrice,
            'min_toppings' => 0,
            'max_toppings' => 0,
            'image_path' => (string)($product['images'][0]['src'] ?? ''),
            'is_available' => 0,
            'is_active' => 1,
            'sort_order' => $sort,
        ];

        $existingExternal = $this->repo->findCatalogMapByExternal($branchId, (int)($product['id'] ?? 0), 0);
        $menuItemId = $this->repo->upsertMenuItem($menuItemData);
        $summary[$existingExternal ? 'updated_items' : 'inserted_items']++;

        $this->repo->upsertBranchMenuOverride(
            $branchId,
            $menuItemId,
            $basePrice,
            $availability ? 1 : 0,
            'Imported from WooCommerce'
        );
        $this->repo->upsertCatalogMap(
            $branchId,
            $menuItemId,
            0,
            (int)($product['id'] ?? 0),
            0,
            $this->nullableString($product['sku'] ?? null),
            $this->nullableFloat($product['stock_quantity'] ?? null),
            $this->nullableString($product['stock_status'] ?? null),
            $basePrice,
            $product
        );

        if (($product['type'] ?? '') !== 'variable') {
            return;
        }

        $variationResult = $this->pullAllVariations($branchId, (int)($product['id'] ?? 0));
        if (!$variationResult['success']) {
            $this->repo->logSync(
                $branchId,
                'product',
                'live.catalog_variations',
                'failed',
                (string)($product['id'] ?? ''),
                ['product' => $productName],
                $variationResult['response'] ?? ['message' => $variationResult['message'] ?? 'Unknown variation pull error.']
            );
            return;
        }

        $variations = $variationResult['data'];
        if ($variations === []) {
            return;
        }

        $baseVariantPrice = min(array_map(fn(array $row): float => $this->resolveWooProductPrice($row), $variations));
        if ($baseVariantPrice > 0 && abs($baseVariantPrice - (float)$menuItemData['price']) > 0.0001) {
            $menuItemData['price'] = $baseVariantPrice;
            $menuItemId = $this->repo->upsertMenuItem($menuItemData);
            $this->repo->upsertBranchMenuOverride($branchId, $menuItemId, $baseVariantPrice, $availability ? 1 : 0, 'Imported from WooCommerce');
        }

        foreach ($variations as $variantSort => $variation) {
            $variationId = (int)($variation['id'] ?? 0);
            $existingVariantMap = $this->repo->findCatalogMapByExternal($branchId, (int)($product['id'] ?? 0), $variationId);
            $variantLabel = $this->buildVariationLabel($variation);
            $variantPrice = $this->resolveWooProductPrice($variation);
            $variantId = $this->repo->upsertVariant([
                'menu_item_id' => $menuItemId,
                'label' => $variantLabel,
                'slug' => $this->slugify($variantLabel),
                'price_delta' => round($variantPrice - (float)$menuItemData['price'], 2),
                'sort_order' => $variantSort,
                'is_active' => $this->resolveWooAvailability($variation) ? 1 : 0,
            ]);

            $summary[$existingVariantMap ? 'updated_variants' : 'inserted_variants']++;
            $this->repo->upsertCatalogMap(
                $branchId,
                $menuItemId,
                $variantId,
                (int)($product['id'] ?? 0),
                $variationId,
                $this->nullableString($variation['sku'] ?? null),
                $this->nullableFloat($variation['stock_quantity'] ?? null),
                $this->nullableString($variation['stock_status'] ?? null),
                $variantPrice,
                $variation
            );
        }
    }

    private function pushOrderLive(array $order, string $eventName): array
    {
        $branchId = (int)($order['branch_id'] ?? 0);
        $validation = $this->buildWooOrderPayload($branchId, $order);
        if (!$validation['success']) {
            $this->repo->logSync(
                $branchId,
                'order',
                $eventName,
                'failed',
                (string)($order['order_number'] ?? $order['id'] ?? ''),
                $this->buildOrderPayload($order),
                ['message' => $validation['message']],
                'outbound'
            );
            return $validation;
        }

        $result = $this->client->createOrder($branchId, $validation['payload']);
        $status = $result['success'] ? 'success' : 'failed';
        $message = $result['success']
            ? 'Order berhasil dikirim ke WooCommerce.'
            : ('Gagal mengirim order ke WooCommerce: ' . ($result['error'] ?: ('HTTP ' . (string)$result['status'])));

        $this->repo->logSync(
            $branchId,
            'order',
            $eventName,
            $status,
            (string)($order['order_number'] ?? $order['id'] ?? ''),
            $result['request_preview'] ?? $validation['payload'],
            $result['response_preview'] ?? ['message' => $message],
            'outbound'
        );

        return [
            'success' => $result['success'],
            'message' => $message,
            'data' => $result['data'] ?? null,
        ];
    }

    private function buildWooOrderPayload(int $branchId, array $order): array
    {
        $lineItems = [];
        $missingMappings = [];
        foreach ((array)($order['items'] ?? []) as $item) {
            $menuItemId = (int)($item['menu_item_id'] ?? 0);
            $variantId = (int)($item['variant_id'] ?? 0);
            $map = $this->repo->findCatalogMapByLocal($branchId, $menuItemId, $variantId);
            if (!$map && $variantId > 0) {
                $map = $this->repo->findCatalogMapByLocal($branchId, $menuItemId, 0);
            }
            if (!$map) {
                $missingMappings[] = (string)($item['menu_name'] ?? ('menu_item#' . $menuItemId));
                continue;
            }

            $line = [
                'product_id' => (int)($map['external_product_id'] ?? 0),
                'quantity' => (int)($item['quantity'] ?? 1),
                'subtotal' => number_format((float)($item['subtotal'] ?? 0), 2, '.', ''),
                'total' => number_format((float)($item['subtotal'] ?? 0), 2, '.', ''),
                'meta_data' => [
                    ['key' => 'kopibot_menu_item_id', 'value' => $menuItemId],
                    ['key' => 'kopibot_variant_id', 'value' => $variantId],
                    ['key' => 'kopibot_variant_label', 'value' => (string)($item['variant_label'] ?? '')],
                    ['key' => 'kopibot_notes', 'value' => (string)($item['notes'] ?? '')],
                ],
            ];
            if ((int)($map['external_variation_id'] ?? 0) > 0) {
                $line['variation_id'] = (int)$map['external_variation_id'];
            }
            $lineItems[] = $line;
        }

        if ($missingMappings !== []) {
            return [
                'success' => false,
                'message' => 'Sebagian item belum memiliki mapping WooCommerce: ' . implode(', ', $missingMappings),
            ];
        }

        if ($lineItems === []) {
            return ['success' => false, 'message' => 'Order tidak memiliki line items yang bisa dikirim ke WooCommerce.'];
        }

        $billingFirstName = (string)($order['customer_name'] ?? '');
        $billingLastName = '';
        if (str_contains(trim($billingFirstName), ' ')) {
            $parts = preg_split('/\s+/', trim($billingFirstName)) ?: [];
            $billingFirstName = array_shift($parts) ?: $billingFirstName;
            $billingLastName = implode(' ', $parts);
        }

        return [
            'success' => true,
            'payload' => [
                'status' => $this->mapInternalOrderStatusToWoo((string)($order['order_status'] ?? 'pending')),
                'set_paid' => (string)($order['payment_status'] ?? '') === 'paid',
                'customer_note' => (string)($order['notes'] ?? ''),
                'billing' => [
                    'first_name' => $billingFirstName,
                    'last_name' => $billingLastName,
                    'email' => (string)($order['customer_email'] ?? ''),
                    'phone' => (string)($order['customer_wa'] ?? ''),
                    'address_1' => (string)($order['delivery_address'] ?? ''),
                ],
                'shipping' => [
                    'first_name' => (string)($order['customer_name'] ?? ''),
                    'address_1' => (string)($order['delivery_address'] ?? ''),
                ],
                'line_items' => $lineItems,
                'meta_data' => [
                    ['key' => 'kopibot_order_number', 'value' => (string)($order['order_number'] ?? '')],
                    ['key' => 'kopibot_branch_id', 'value' => (int)($order['branch_id'] ?? 0)],
                    ['key' => 'kopibot_channel', 'value' => (string)($order['channel'] ?? '')],
                    ['key' => 'kopibot_customer_id', 'value' => (int)($order['customer_id'] ?? 0)],
                    ['key' => 'kopibot_subtotal', 'value' => (float)($order['subtotal'] ?? 0)],
                    ['key' => 'kopibot_discount_amount', 'value' => (float)($order['discount_amount'] ?? 0)],
                    ['key' => 'kopibot_ppn_amount', 'value' => (float)($order['ppn_amount'] ?? 0)],
                    ['key' => 'kopibot_total_amount', 'value' => (float)($order['total_amount'] ?? 0)],
                ],
            ],
        ];
    }

    private function buildOrderPayload(array $order): array
    {
        $payload = [
            'id' => (int)($order['id'] ?? 0),
            'order_number' => (string)($order['order_number'] ?? ''),
            'branch_id' => (int)($order['branch_id'] ?? 0),
            'customer_id' => (int)($order['customer_id'] ?? 0),
            'customer_name' => (string)($order['customer_name'] ?? ''),
            'customer_email' => (string)($order['customer_email'] ?? ''),
            'customer_wa' => (string)($order['customer_wa'] ?? ''),
            'channel' => (string)($order['channel'] ?? ''),
            'subtotal' => (float)($order['subtotal'] ?? 0),
            'discount_amount' => (float)($order['discount_amount'] ?? 0),
            'ppn_amount' => (float)($order['ppn_amount'] ?? 0),
            'total_amount' => (float)($order['total_amount'] ?? 0),
            'order_status' => (string)($order['order_status'] ?? ''),
            'payment_status' => (string)($order['payment_status'] ?? ''),
            'created_at' => (string)($order['created_at'] ?? ''),
        ];

        if (!empty($order['items']) && is_array($order['items'])) {
            $payload['items'] = array_map(static function (array $item): array {
                return [
                    'menu_item_id' => (int)($item['menu_item_id'] ?? 0),
                    'variant_id' => (int)($item['variant_id'] ?? 0),
                    'menu_name' => (string)($item['menu_name'] ?? ''),
                    'variant_label' => (string)($item['variant_label'] ?? ''),
                    'quantity' => (int)($item['quantity'] ?? 0),
                    'unit_price' => (float)($item['unit_price'] ?? 0),
                    'subtotal' => (float)($item['subtotal'] ?? 0),
                    'notes' => (string)($item['notes'] ?? ''),
                ];
            }, $order['items']);
        }

        return $payload;
    }

    private function parseMap(string $map): array
    {
        $result = [];
        foreach (explode(',', $map) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || !str_contains($pair, ':')) {
                continue;
            }
            [$left, $right] = array_map('trim', explode(':', $pair, 2));
            if ($left !== '' && $right !== '') {
                $result[strtolower($left)] = $right;
            }
        }

        return $result;
    }

    private function resolveOrderNumber(array $payload): string
    {
        $candidates = [
            $payload['order_number'] ?? null,
            $payload['number'] ?? null,
            $payload['order_key'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && trim((string)$candidate) !== '') {
                return trim((string)$candidate);
            }
        }

        if (is_array($payload['meta_data'] ?? null)) {
            foreach ($payload['meta_data'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (($row['key'] ?? '') === 'kopibot_order_number' && is_scalar($row['value'] ?? null) && trim((string)$row['value']) !== '') {
                    return trim((string)$row['value']);
                }
            }
        }

        return '';
    }

    private function resolveRemoteOrderStatus(array $payload): string
    {
        $value = $payload['status'] ?? $payload['order_status'] ?? '';
        return is_scalar($value) ? strtolower(trim((string)$value)) : '';
    }

    private function resolveRemotePaymentStatus(array $payload): string
    {
        $candidates = [
            $payload['payment_status'] ?? null,
            $payload['financial_status'] ?? null,
            $payload['transaction_status'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && trim((string)$candidate) !== '') {
                return strtolower(trim((string)$candidate));
            }
        }

        if (($payload['date_paid'] ?? null) !== null && (string)$payload['date_paid'] !== '') {
            return 'paid';
        }

        return '';
    }

    private function pullAllVariations(int $branchId, int $productId): array
    {
        $page = 1;
        $all = [];
        do {
            $result = $this->client->pullVariations($branchId, $productId, $page, 100);
            if (!$result['success']) {
                return [
                    'success' => false,
                    'message' => 'Gagal menarik variasi WooCommerce.',
                    'response' => $result['response_preview'] ?? [],
                    'data' => [],
                ];
            }
            $rows = is_array($result['data']) ? $result['data'] : [];
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $all[] = $row;
                }
            }
            $page++;
        } while (!empty($rows));

        return ['success' => true, 'data' => $all];
    }

    private function resolveWooProductPrice(array $product): float
    {
        foreach (['price', 'regular_price', 'sale_price'] as $key) {
            $value = $product[$key] ?? null;
            if (is_scalar($value) && trim((string)$value) !== '') {
                return (float)$value;
            }
        }

        return 0.0;
    }

    private function resolveWooAvailability(array $product): bool
    {
        $stockStatus = strtolower(trim((string)($product['stock_status'] ?? '')));
        if ($stockStatus !== '') {
            return $stockStatus === 'instock' || $stockStatus === 'onbackorder';
        }
        return (bool)($product['purchasable'] ?? true);
    }

    private function buildVariationLabel(array $variation): string
    {
        $parts = [];
        foreach ((array)($variation['attributes'] ?? []) as $attribute) {
            if (is_array($attribute) && !empty($attribute['option'])) {
                $parts[] = (string)$attribute['option'];
            }
        }

        return $parts !== [] ? implode(' / ', $parts) : ('Variation ' . (string)($variation['id'] ?? ''));
    }

    private function cleanDescription(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $text = trim((string)$value);
        return $text !== '' ? $text : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (float)$value;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;
        $text = trim($text, '-');
        return $text !== '' ? $text : 'item';
    }

    private function mapInternalOrderStatusToWoo(string $status): string
    {
        $map = [
            'pending' => 'pending',
            'processing' => 'processing',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
        ];

        return $map[strtolower($status)] ?? 'processing';
    }
}
