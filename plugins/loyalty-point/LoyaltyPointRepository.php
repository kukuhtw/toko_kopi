<?php

declare(strict_types=1);

use App\Config\Database;
use App\Plugin\HookManager;

class LoyaltyPointRepository
{
    private static bool $schemaReady = false;

    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        $db = Database::getInstance();

        $db->exec(
            'CREATE TABLE IF NOT EXISTS loyalty_point_accounts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                branch_id INT UNSIGNED NOT NULL,
                customer_id INT UNSIGNED NOT NULL,
                balance_points INT NOT NULL DEFAULT 0,
                lifetime_points INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_loyalty_branch_customer (branch_id, customer_id),
                INDEX idx_loyalty_branch (branch_id),
                INDEX idx_loyalty_customer (customer_id),
                CONSTRAINT fk_loyalty_account_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
                CONSTRAINT fk_loyalty_account_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS loyalty_point_transactions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                branch_id INT UNSIGNED NOT NULL,
                customer_id INT UNSIGNED NOT NULL,
                order_id INT UNSIGNED NULL,
                points INT NOT NULL,
                transaction_type VARCHAR(30) NOT NULL DEFAULT "earn",
                description VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_loyalty_tx_branch (branch_id),
                INDEX idx_loyalty_tx_customer (customer_id),
                INDEX idx_loyalty_tx_order (order_id),
                CONSTRAINT fk_loyalty_tx_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
                CONSTRAINT fk_loyalty_tx_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                CONSTRAINT fk_loyalty_tx_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->ensureColumn('carts', 'loyalty_points_redeemed', 'INT NOT NULL DEFAULT 0 AFTER discount_amount');
        $this->ensureColumn('carts', 'loyalty_discount_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER loyalty_points_redeemed');
        $this->ensureColumn('orders', 'loyalty_points_redeemed', 'INT NOT NULL DEFAULT 0 AFTER discount_amount');
        $this->ensureColumn('orders', 'loyalty_discount_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER loyalty_points_redeemed');

        self::$schemaReady = true;
    }

    public function hasEarnTransactionForOrder(int $orderId): bool
    {
        $this->ensureSchema();

        $stmt = Database::getInstance()->prepare(
            'SELECT id
             FROM loyalty_point_transactions
             WHERE order_id = ? AND transaction_type = "earn"
             LIMIT 1'
        );
        $stmt->execute([$orderId]);

        return (bool) $stmt->fetchColumn();
    }

    public function awardPoints(int $branchId, int $customerId, int $orderId, int $points, string $description): void
    {
        if ($points <= 0) {
            return;
        }

        $this->ensureSchema();
        $db = Database::getInstance();

        $db->beginTransaction();

        try {
            $db->prepare(
                'INSERT INTO loyalty_point_accounts (branch_id, customer_id, balance_points, lifetime_points)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    balance_points = balance_points + VALUES(balance_points),
                    lifetime_points = lifetime_points + VALUES(lifetime_points),
                    updated_at = CURRENT_TIMESTAMP'
            )->execute([$branchId, $customerId, $points, $points]);

            $db->prepare(
                'INSERT INTO loyalty_point_transactions (branch_id, customer_id, order_id, points, transaction_type, description)
                 VALUES (?, ?, ?, ?, "earn", ?)'
            )->execute([$branchId, $customerId, $orderId, $points, $description]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $this->emitPointsChanged($branchId, $customerId, $orderId, $points, 'earn', $description);
    }

    public function redeemPoints(int $branchId, int $customerId, int $orderId, int $points, string $description): void
    {
        if ($points <= 0) {
            return;
        }

        $this->ensureSchema();
        $db = Database::getInstance();

        $db->beginTransaction();

        try {
            $db->prepare(
                'INSERT INTO loyalty_point_accounts (branch_id, customer_id, balance_points, lifetime_points)
                 VALUES (?, ?, 0, 0)
                 ON DUPLICATE KEY UPDATE
                    balance_points = GREATEST(0, balance_points - ?),
                    updated_at = CURRENT_TIMESTAMP'
            )->execute([$branchId, $customerId, $points]);

            $db->prepare(
                'INSERT INTO loyalty_point_transactions (branch_id, customer_id, order_id, points, transaction_type, description)
                 VALUES (?, ?, ?, ?, "redeem", ?)'
            )->execute([$branchId, $customerId, $orderId, -$points, $description]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $this->emitPointsChanged($branchId, $customerId, $orderId, -$points, 'redeem', $description);
    }

    public function refundRedeemedPoints(int $branchId, int $customerId, int $orderId, int $points, string $description): void
    {
        if ($points <= 0) {
            return;
        }

        $this->ensureSchema();
        $db = Database::getInstance();

        $db->beginTransaction();

        try {
            $db->prepare(
                'INSERT INTO loyalty_point_accounts (branch_id, customer_id, balance_points, lifetime_points)
                 VALUES (?, ?, ?, 0)
                 ON DUPLICATE KEY UPDATE
                    balance_points = balance_points + VALUES(balance_points),
                    updated_at = CURRENT_TIMESTAMP'
            )->execute([$branchId, $customerId, $points]);

            $db->prepare(
                'INSERT INTO loyalty_point_transactions (branch_id, customer_id, order_id, points, transaction_type, description)
                 VALUES (?, ?, ?, ?, "refund", ?)'
            )->execute([$branchId, $customerId, $orderId, $points, $description]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $this->emitPointsChanged($branchId, $customerId, $orderId, $points, 'refund', $description);
    }

    public function getBalance(int $branchId, int $customerId): array
    {
        $this->ensureSchema();

        $stmt = Database::getInstance()->prepare(
            'SELECT balance_points, lifetime_points, updated_at
             FROM loyalty_point_accounts
             WHERE branch_id = ? AND customer_id = ?
             LIMIT 1'
        );
        $stmt->execute([$branchId, $customerId]);
        $row = $stmt->fetch();

        return $row ?: [
            'balance_points'  => 0,
            'lifetime_points' => 0,
            'updated_at'      => null,
        ];
    }

    public function applyRedemptionToCart(int $cartId, int $points, float $discount): void
    {
        $this->ensureSchema();

        $cart = Database::getInstance()->prepare(
            'SELECT discount_amount, loyalty_discount_amount
             FROM carts
             WHERE id = ?
             LIMIT 1'
        );
        $cart->execute([$cartId]);
        $row = $cart->fetch() ?: ['discount_amount' => 0, 'loyalty_discount_amount' => 0];

        $currentLoyalty = (float)($row['loyalty_discount_amount'] ?? 0);
        $promoDiscount  = max(0.0, (float)($row['discount_amount'] ?? 0) - $currentLoyalty);
        $totalDiscount  = $promoDiscount + max(0.0, $discount);

        Database::getInstance()->prepare(
            'UPDATE carts
             SET loyalty_points_redeemed = ?, loyalty_discount_amount = ?, discount_amount = ?
             WHERE id = ?'
        )->execute([$points, $discount, $totalDiscount, $cartId]);
    }

    public function clearRedemptionFromCart(int $cartId): void
    {
        $this->ensureSchema();

        $cart = Database::getInstance()->prepare(
            'SELECT discount_amount, loyalty_discount_amount
             FROM carts
             WHERE id = ?
             LIMIT 1'
        );
        $cart->execute([$cartId]);
        $row = $cart->fetch() ?: ['discount_amount' => 0, 'loyalty_discount_amount' => 0];

        $currentLoyalty = (float)($row['loyalty_discount_amount'] ?? 0);
        $promoDiscount  = max(0.0, (float)($row['discount_amount'] ?? 0) - $currentLoyalty);

        Database::getInstance()->prepare(
            'UPDATE carts
             SET loyalty_points_redeemed = 0, loyalty_discount_amount = 0, discount_amount = ?
             WHERE id = ?'
        )->execute([$promoDiscount, $cartId]);
    }

    public function hasTransactionForOrder(int $orderId, string $type): bool
    {
        $this->ensureSchema();

        $stmt = Database::getInstance()->prepare(
            'SELECT id
             FROM loyalty_point_transactions
             WHERE order_id = ? AND transaction_type = ?
             LIMIT 1'
        );
        $stmt->execute([$orderId, $type]);

        return (bool) $stmt->fetchColumn();
    }

    public function getBranchSummary(int $branchId): array
    {
        $this->ensureSchema();

        $summary = Database::getInstance()->prepare(
            'SELECT
                COUNT(*) AS member_count,
                COALESCE(SUM(balance_points), 0) AS total_balance_points,
                COALESCE(SUM(lifetime_points), 0) AS total_lifetime_points
             FROM loyalty_point_accounts
             WHERE branch_id = ?'
        );
        $summary->execute([$branchId]);
        $row = $summary->fetch() ?: [];

        $recent = Database::getInstance()->prepare(
            'SELECT
                c.name,
                c.identifier,
                lpa.balance_points,
                lpa.lifetime_points
             FROM loyalty_point_accounts lpa
             JOIN customers c ON c.id = lpa.customer_id
             WHERE lpa.branch_id = ?
             ORDER BY lpa.balance_points DESC, lpa.updated_at DESC
             LIMIT 5'
        );
        $recent->execute([$branchId]);

        $row['top_members'] = $recent->fetchAll();

        return $row;
    }

    public function fetchCustomerSummaries(int $branchId, string $query = '', int $limit = 25, int $offset = 0): array
    {
        $this->ensureSchema();

        $where = ['lpa.branch_id = ?'];
        $params = [$branchId];

        if ($query !== '') {
            $where[] = '(c.name LIKE ? OR c.identifier LIKE ? OR c.whatsapp LIKE ? OR c.email LIKE ?)';
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $params[] = $limit;
        $params[] = $offset;

        $stmt = Database::getInstance()->prepare(
            'SELECT
                lpa.customer_id,
                c.name,
                c.identifier,
                c.whatsapp,
                c.email,
                lpa.balance_points,
                lpa.lifetime_points,
                lpa.updated_at,
                MAX(lpt.created_at) AS last_transaction_at,
                MAX(o.created_at) AS last_order_at,
                MAX(cnl.created_at) AS last_notification_at
             FROM loyalty_point_accounts lpa
             JOIN customers c ON c.id = lpa.customer_id
             LEFT JOIN loyalty_point_transactions lpt
                ON lpt.branch_id = lpa.branch_id
               AND lpt.customer_id = lpa.customer_id
             LEFT JOIN orders o
                ON o.branch_id = lpa.branch_id
               AND o.customer_id = lpa.customer_id
             LEFT JOIN crm_notification_logs cnl
                ON cnl.branch_id = lpa.branch_id
               AND cnl.customer_id = lpa.customer_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY lpa.customer_id, c.name, c.identifier, c.whatsapp, c.email, lpa.balance_points, lpa.lifetime_points, lpa.updated_at
             ORDER BY
                GREATEST(
                    COALESCE(MAX(cnl.created_at), "1000-01-01 00:00:00"),
                    COALESCE(MAX(o.created_at), "1000-01-01 00:00:00"),
                    COALESCE(MAX(lpt.created_at), "1000-01-01 00:00:00"),
                    COALESCE(lpa.updated_at, "1000-01-01 00:00:00")
                ) DESC,
                c.id DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countCustomerSummaries(int $branchId, string $query = ''): int
    {
        $this->ensureSchema();

        $where = ['branch_id = ?'];
        $params = [$branchId];

        if ($query !== '') {
            $where[] = 'customer_id IN (
                SELECT id FROM customers
                WHERE name LIKE ? OR identifier LIKE ? OR whatsapp LIKE ? OR email LIKE ?
            )';
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $stmt = Database::getInstance()->prepare(
            'SELECT COUNT(*)
             FROM loyalty_point_accounts
             WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function getCustomerTransactions(int $branchId, int $customerId, int $limit = 20): array
    {
        $this->ensureSchema();

        $stmt = Database::getInstance()->prepare(
            'SELECT
                lpt.*,
                o.order_number,
                o.order_status,
                o.payment_status
             FROM loyalty_point_transactions lpt
             LEFT JOIN orders o ON o.id = lpt.order_id
             WHERE lpt.branch_id = ? AND lpt.customer_id = ?
             ORDER BY lpt.created_at DESC, lpt.id DESC
             LIMIT ?'
        );
        $stmt->execute([$branchId, $customerId, $limit]);

        return $stmt->fetchAll();
    }

    public function getOrderTransactions(int $orderId): array
    {
        $this->ensureSchema();

        $stmt = Database::getInstance()->prepare(
            'SELECT *
             FROM loyalty_point_transactions
             WHERE order_id = ?
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute([$orderId]);

        return $stmt->fetchAll();
    }

    public function getCustomerAccount(int $branchId, int $customerId): array
    {
        $this->ensureSchema();

        $stmt = Database::getInstance()->prepare(
            'SELECT
                lpa.customer_id,
                c.name,
                c.identifier,
                c.whatsapp,
                c.email,
                lpa.balance_points,
                lpa.lifetime_points,
                lpa.updated_at
             FROM loyalty_point_accounts lpa
             JOIN customers c ON c.id = lpa.customer_id
             WHERE lpa.branch_id = ? AND lpa.customer_id = ?
             LIMIT 1'
        );
        $stmt->execute([$branchId, $customerId]);
        $row = $stmt->fetch();

        return $row ?: [];
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?
             LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        if ($stmt->fetchColumn()) {
            return;
        }

        Database::getInstance()->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }

    private function emitPointsChanged(
        int $branchId,
        int $customerId,
        int $orderId,
        int $pointsDelta,
        string $transactionType,
        string $description
    ): void {
        $balance = $this->getBalance($branchId, $customerId);

        HookManager::doAction('loyalty.points_changed', [
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'points_delta' => $pointsDelta,
            'transaction_type' => $transactionType,
            'description' => $description,
            'balance_points' => (int)($balance['balance_points'] ?? 0),
            'lifetime_points' => (int)($balance['lifetime_points'] ?? 0),
        ]);
    }
}
