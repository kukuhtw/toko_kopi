<?php

declare(strict_types=1);

namespace App\Models;

use App\Plugin\HookManager;

class OrderModel extends BaseModel
{
    protected string $table = 'orders';

    public function createFromCart(array $cart, array $cartItems, array $customerData, int $customerId, float $ppnRate = 0.0): int
    {
        $subtotal      = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $cartItems));
        $discount      = (float)($cart['discount_amount'] ?? 0);
        $afterDiscount = max(0.0, $subtotal - $discount);
        $ppnAmount     = $ppnRate > 0 ? round($afterDiscount * $ppnRate / 100, 2) : 0.0;
        $total         = $afterDiscount + $ppnAmount;

        // Filter: plugin bisa modifikasi total akhir (tambah biaya, diskon custom, dll.)
        $total    = (float) HookManager::applyFilters('cart.total', $total, $cartItems, $cart);
        $orderNum = $this->generateOrderNumber();

        $orderData = [
            'order_number'    => $orderNum,
            'branch_id'       => $cart['branch_id'],
            'customer_id'     => $customerId,
            'channel'         => $cart['channel'] ?? 'web',
            'customer_name'   => $customerData['name'],
            'customer_email'  => $customerData['email']       ?? null,
            'customer_wa'     => $customerData['whatsapp']    ?? null,
            'delivery_address'=> $customerData['address']     ?? null,
            'postal_code'     => $customerData['postal_code'] ?? null,
            'subtotal'        => $subtotal,
            'discount_amount' => $discount,
            'ppn_rate'        => $ppnRate,
            'ppn_amount'      => $ppnAmount,
            'total_amount'    => $total,
            'promo_code'      => $cart['promo_code']  ?? null,
            'notes'           => $cart['notes']       ?? null,
        ];

        $orderData = (array) HookManager::applyFilters(
            'order.before_create',
            $orderData,
            $cart,
            $cartItems,
            $customerData,
            $customerId,
            $ppnRate
        );

        $orderId = $this->insert($orderData);

        // Insert order items
        foreach ($cartItems as $item) {
            $this->query(
                'INSERT INTO order_items (order_id, menu_item_id, menu_name, variant_id, variant_label, quantity, unit_price, subtotal, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $orderId,
                    $item['menu_item_id'],
                    $item['base_name'] ?? $item['name'],
                    $item['variant_id'] ?? null,
                    $item['variant_label'] ?? null,
                    $item['quantity'],
                    $item['unit_price'],
                    $item['quantity'] * $item['unit_price'],
                    $item['notes'] ?? null,
                ]
            );
        }

        // Log status
        $this->query(
            'INSERT INTO order_status_logs (order_id, old_status, new_status) VALUES (?, NULL, ?)',
            [$orderId, 'pending']
        );

        // Action: order baru berhasil dibuat
        HookManager::doAction('order.created', $this->getWithItems($orderId));

        return $orderId;
    }

    public function countByCustomerMonths(int $customerId, int $branchId, int $months): int
    {
        $result = $this->query(
            'SELECT COUNT(*) AS cnt FROM orders
             WHERE customer_id = ? AND branch_id = ?
               AND order_status != "cancelled"
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)',
            [$customerId, $branchId, $months]
        )->fetch();
        return (int)($result['cnt'] ?? 0);
    }

    private function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date   = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        return "{$prefix}-{$date}-{$random}";
    }

    public function getWithItems(int $orderId): array|false
    {
        $order = $this->find($orderId);
        if (!$order) { return false; }

        $order['items'] = $this->query(
            'SELECT * FROM order_items WHERE order_id = ?',
            [$orderId]
        )->fetchAll();

        return $order;
    }

    public function getByBranch(int $branchId, int $limit = 50, int $offset = 0): array
    {
        return $this->query(
            'SELECT o.*, c.name AS customer_display_name
             FROM orders o
             JOIN customers c ON o.customer_id = c.id
             WHERE o.branch_id = ?
             ORDER BY o.created_at DESC
             LIMIT ? OFFSET ?',
            [$branchId, $limit, $offset]
        )->fetchAll();
    }

    public function searchByBranch(int $branchId, array $f, int $limit, int $offset): array
    {
        [$clause, $params] = $this->buildSearchClause($branchId, $f);
        $params[] = $limit;
        $params[] = $offset;
        return $this->query(
            "SELECT o.* FROM orders o WHERE {$clause} ORDER BY o.created_at DESC LIMIT ? OFFSET ?",
            $params
        )->fetchAll();
    }

    public function countSearchByBranch(int $branchId, array $f): int
    {
        [$clause, $params] = $this->buildSearchClause($branchId, $f);
        return (int) $this->query(
            "SELECT COUNT(*) FROM orders o WHERE {$clause}",
            $params
        )->fetchColumn();
    }

    private function buildSearchClause(int $branchId, array $f): array
    {
        $where  = ['o.branch_id = ?'];
        $params = [$branchId];

        if (!empty($f['q'])) {
            $where[]  = '(o.order_number LIKE ? OR o.customer_name LIKE ?)';
            $like     = '%' . $f['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($f['status'])) {
            $where[]  = 'o.order_status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['payment'])) {
            $where[]  = 'o.payment_status = ?';
            $params[] = $f['payment'];
        }
        if (!empty($f['date_from'])) {
            $where[]  = 'DATE(o.created_at) >= ?';
            $params[] = $f['date_from'];
        }
        if (!empty($f['date_to'])) {
            $where[]  = 'DATE(o.created_at) <= ?';
            $params[] = $f['date_to'];
        }

        return [implode(' AND ', $where), $params];
    }

    public function getAll(int $limit = 100, int $offset = 0): array
    {
        return $this->query(
            'SELECT o.*, b.name AS branch_name, c.name AS customer_display_name
             FROM orders o
             JOIN branches b ON o.branch_id = b.id
             JOIN customers c ON o.customer_id = c.id
             ORDER BY o.created_at DESC
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        )->fetchAll();
    }

    public function updateStatus(int $orderId, string $status, ?int $changedBy = null): void
    {
        $order = $this->find($orderId);
        if (!$order) { return; }

        $oldStatus = $order['order_status'];
        $this->update($orderId, ['order_status' => $status]);

        $this->query(
            'INSERT INTO order_status_logs (order_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)',
            [$orderId, $oldStatus, $status, $changedBy]
        );

        $updated = $this->find($orderId);
        HookManager::doAction('order.status_changed', $updated, $oldStatus, $status);

        if ($status === 'completed') {
            HookManager::doAction('order.completed', $updated);
        }
    }

    public function updatePayment(int $orderId, string $paymentStatus, ?int $changedBy = null): void
    {
        $data = ['payment_status' => $paymentStatus];
        if ($paymentStatus === 'paid') {
            $data['paid_at'] = date('Y-m-d H:i:s');
        }
        $this->update($orderId, $data);

        $this->query(
            'INSERT INTO order_status_logs (order_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)',
            [$orderId, null, 'payment_' . $paymentStatus, $changedBy, 'Payment status update']
        );

        HookManager::doAction('order.payment_updated', $this->find($orderId), $paymentStatus);
    }

    public function updateAdminNotes(int $orderId, string $notes): void
    {
        $this->update($orderId, ['admin_notes' => $notes ?: null]);
    }

    public function findByOrderNumber(string $orderNumber): array|false
    {
        return $this->query(
            'SELECT * FROM orders WHERE order_number = ? LIMIT 1',
            [$orderNumber]
        )->fetch() ?: false;
    }

    public function getCustomerOrders(int $customerId, int $limit = 10): array
    {
        return $this->query(
            'SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT ?',
            [$customerId, $limit]
        )->fetchAll();
    }

    public function getRecentMenuItemIdsForBranch(int $customerId, int $branchId, int $limit = 5): array
    {
        $rows = $this->query(
            'SELECT oi.menu_item_id
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.customer_id = ? AND o.branch_id = ?
             GROUP BY oi.menu_item_id
             ORDER BY MAX(o.created_at) DESC, SUM(oi.quantity) DESC
             LIMIT ?',
            [$customerId, $branchId, $limit]
        )->fetchAll();

        return array_values(array_map(static fn(array $row): int => (int)($row['menu_item_id'] ?? 0), $rows));
    }

    public function getStats(int $branchId): array
    {
        $row = $this->query(
            'SELECT
                COUNT(*) AS total_orders,
                SUM(total_amount) AS total_revenue,
                SUM(CASE WHEN payment_status = "paid" THEN total_amount ELSE 0 END) AS paid_revenue,
                SUM(CASE WHEN order_status = "pending" THEN 1 ELSE 0 END) AS pending_orders,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_orders
             FROM orders WHERE branch_id = ?',
            [$branchId]
        )->fetch();
        return $row ?: [];
    }
}
