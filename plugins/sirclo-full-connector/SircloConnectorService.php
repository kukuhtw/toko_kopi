<?php

declare(strict_types=1);

use App\Models\CustomerModel;
use App\Models\MenuModel;
use App\Models\OrderModel;

final class SircloConnectorService
{
    private SircloConnectorRepository $repo;

    public function __construct(?SircloConnectorRepository $repo = null)
    {
        $this->repo = $repo ?? new SircloConnectorRepository();
    }

    public function isEnabled(int $branchId): bool
    {
        return $branchId > 0 && $this->repo->getBranchSetting($branchId, 'is_active', '0') === '1';
    }

    public function queueOrderSync(array $order, string $eventName): void
    {
        $branchId = (int)($order['branch_id'] ?? 0);
        if (!$this->isEnabled($branchId)) {
            return;
        }

        $payload = $this->buildOrderPayload($order);
        $status = $this->hasConnectionConfig($branchId) ? 'pending' : 'config_missing';

        $this->repo->logSync(
            $branchId,
            'order',
            $eventName,
            $status,
            (string)($order['order_number'] ?? $order['id'] ?? ''),
            $payload,
            $status === 'pending'
                ? ['message' => 'Order queued for Sirclo sync.']
                : ['message' => 'Sirclo credentials are incomplete.']
        );
    }

    public function syncProductsSnapshot(int $branchId): void
    {
        if (!$this->isEnabled($branchId)) {
            return;
        }

        $menuModel = new MenuModel();
        $items = $menuModel->getMenuForBranch($branchId);
        $categories = $menuModel->getCategories();
        $status = $this->hasConnectionConfig($branchId) ? 'pending' : 'config_missing';

        $this->repo->logSync(
            $branchId,
            'product',
            'manual.catalog_sync',
            $status,
            'branch:' . $branchId,
            [
                'branch_id' => $branchId,
                'category_count' => count($categories),
                'item_count' => count($items),
                'sample_items' => array_slice(array_map(static function (array $item): array {
                    return [
                        'id' => (int)($item['id'] ?? 0),
                        'name' => (string)($item['name'] ?? ''),
                        'price' => (float)($item['effective_price'] ?? $item['price'] ?? 0),
                        'available' => (int)($item['effective_available'] ?? 0),
                    ];
                }, $items), 0, 5),
            ],
            ['message' => 'Product catalog snapshot prepared.']
        );
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
        $rows->bindValue(1, $branchId, \PDO::PARAM_INT);
        $rows->bindValue(2, $limit, \PDO::PARAM_INT);
        $rows->execute();
        $customers = $rows->fetchAll();

        $status = $this->hasConnectionConfig($branchId) ? 'pending' : 'config_missing';
        $this->repo->logSync(
            $branchId,
            'customer',
            'manual.customer_sync',
            $status,
            'branch:' . $branchId,
            [
                'branch_id' => $branchId,
                'customer_count' => count($customers),
                'sample_customers' => array_slice($customers, 0, 5),
            ],
            ['message' => 'Customer snapshot prepared.']
        );
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
            ['message' => 'Recent orders snapshot prepared.']
        );
    }

    public function getConnectionStatus(int $branchId): array
    {
        return [
            'enabled' => $this->isEnabled($branchId),
            'base_url' => $this->repo->getBranchSetting($branchId, 'base_url'),
            'store_id' => $this->repo->getBranchSetting($branchId, 'store_id'),
            'sync_orders' => $this->repo->getBranchSetting($branchId, 'sync_orders', '1') === '1',
            'sync_products' => $this->repo->getBranchSetting($branchId, 'sync_products', '1') === '1',
            'sync_customers' => $this->repo->getBranchSetting($branchId, 'sync_customers', '1') === '1',
            'has_credentials' => $this->hasConnectionConfig($branchId),
            'timeout_seconds' => (int)$this->repo->getGlobalSetting('timeout_seconds', '15'),
            'mode' => $this->repo->getGlobalSetting('connection_mode', 'sandbox'),
        ];
    }

    public function getRepository(): SircloConnectorRepository
    {
        return $this->repo;
    }

    private function hasConnectionConfig(int $branchId): bool
    {
        return $this->repo->getBranchSetting($branchId, 'base_url') !== ''
            && $this->repo->getBranchSetting($branchId, 'store_id') !== ''
            && $this->repo->getBranchSetting($branchId, 'api_key') !== '';
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
                    'menu_name' => (string)($item['menu_name'] ?? ''),
                    'variant_label' => (string)($item['variant_label'] ?? ''),
                    'quantity' => (int)($item['quantity'] ?? 0),
                    'unit_price' => (float)($item['unit_price'] ?? 0),
                    'subtotal' => (float)($item['subtotal'] ?? 0),
                ];
            }, $order['items']);
        }

        return $payload;
    }
}
