<?php

declare(strict_types=1);

namespace App\Models;

class PromoModel extends BaseModel
{
    protected string $table = 'promos';

    private function nowLocal(string $nowLocal): string
    {
        return $nowLocal !== '' ? $nowLocal : date('Y-m-d H:i:s');
    }

    /**
     * IDs of global promos that this branch has overridden or opted out of.
     * A branch_promos row with promo_id set means the branch controls that global promo.
     */
    private function getOverriddenIds(int $branchId): array
    {
        return $this->query(
            'SELECT promo_id FROM branch_promos WHERE branch_id = ? AND promo_id IS NOT NULL',
            [$branchId]
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function buildExcludeSql(array $ids): string
    {
        if (empty($ids)) return '';
        return 'AND id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    }

    public function getActiveForBranch(int $branchId, string $nowLocal = ''): array
    {
        $now         = $this->nowLocal($nowLocal);
        $overrideIds = $this->getOverriddenIds($branchId);
        $excludeSql  = $this->buildExcludeSql($overrideIds);

        // Global promos — skip any that this branch has overridden or opted out of
        $global = $this->query(
            "SELECT *, 'global' AS promo_source FROM promos
             WHERE is_active = 1 {$excludeSql}
               AND (start_date IS NULL OR start_date <= ?)
               AND (end_date IS NULL OR end_date >= ?)
             ORDER BY discount_value DESC",
            array_merge($overrideIds, [$now, $now])
        )->fetchAll();

        // Branch promos — includes both original branch promos and active overrides of globals
        $branch = $this->query(
            "SELECT *, 'branch' AS promo_source FROM branch_promos
             WHERE branch_id = ? AND is_active = 1
               AND (start_date IS NULL OR start_date <= ?)
               AND (end_date IS NULL OR end_date >= ?)
             ORDER BY discount_value DESC",
            [$branchId, $now, $now]
        )->fetchAll();

        return array_merge($global, $branch);
    }

    public function findByCode(string $code, int $branchId, string $nowLocal = ''): array|false
    {
        $now = $this->nowLocal($nowLocal);

        // Branch copy takes precedence (active override or independent branch promo)
        $branch = $this->query(
            "SELECT *, 'branch' AS promo_source FROM branch_promos
             WHERE promo_code = ? AND branch_id = ? AND is_active = 1
               AND (start_date IS NULL OR start_date <= ?)
               AND (end_date IS NULL OR end_date >= ?)
             LIMIT 1",
            [$code, $branchId, $now, $now]
        )->fetch();

        if ($branch) return $branch;

        // Fall back to global — but not if branch has opted out (any record with promo_id link)
        $global = $this->query(
            "SELECT *, 'global' AS promo_source FROM promos
             WHERE promo_code = ? AND is_active = 1
               AND (start_date IS NULL OR start_date <= ?)
               AND (end_date IS NULL OR end_date >= ?)
             LIMIT 1",
            [$code, $now, $now]
        )->fetch();

        if (!$global) return false;

        // Opt-out check: if branch has any record linking this global promo, branch controls it
        $optedOut = $this->query(
            'SELECT id FROM branch_promos WHERE promo_id = ? AND branch_id = ? LIMIT 1',
            [$global['id'], $branchId]
        )->fetch();

        return $optedOut ? false : $global;
    }

    /**
     * Return the auto-apply promo with the highest discount for this cart.
     *
     * Checks three conditions:
     *  1. Period (start_date / end_date) — already in SQL
     *  2. Loyalty — min_tx_count transactions in tx_months months for this customer/branch
     *  3. Category — discount base is category subtotal when applies_to_category_id is set
     *
     * @param array $cartItems  Output of CartModel::getItems() — must include category_id
     */
    public function getBestAutoApply(
        int    $branchId,
        float  $subtotal,
        int    $customerId = 0,
        array  $cartItems  = [],
        string $nowLocal   = ''
    ): array|false {
        $now         = $this->nowLocal($nowLocal);
        $overrideIds = $this->getOverriddenIds($branchId);
        $excludeSql  = $this->buildExcludeSql($overrideIds);

        $global = $this->query(
            "SELECT *, 'global' AS promo_source FROM promos
             WHERE auto_apply = 1 AND is_active = 1 {$excludeSql}
               AND (start_date IS NULL OR start_date <= ?)
               AND (end_date IS NULL OR end_date >= ?)
               AND min_order <= ?",
            array_merge($overrideIds, [$now, $now, $subtotal])
        )->fetchAll();

        $branch = $this->query(
            "SELECT *, 'branch' AS promo_source FROM branch_promos
             WHERE auto_apply = 1 AND is_active = 1 AND branch_id = ?
               AND (start_date IS NULL OR start_date <= ?)
               AND (end_date IS NULL OR end_date >= ?)
               AND min_order <= ?",
            [$branchId, $now, $now, $subtotal]
        )->fetchAll();

        $candidates = array_merge($global, $branch);
        if (empty($candidates)) {
            return false;
        }

        $orderModel = new OrderModel();

        $best         = null;
        $bestDiscount = -1.0;

        foreach ($candidates as $promo) {
            // --- Loyalty check ---
            $minTx   = (int)($promo['min_tx_count'] ?? 0);
            $txMonths = (int)($promo['tx_months'] ?? 0);
            if ($minTx > 0 && $txMonths > 0 && $customerId > 0) {
                $txCount = $orderModel->countByCustomerMonths($customerId, $branchId, $txMonths);
                if ($txCount < $minTx) {
                    continue;
                }
            }

            $d = $this->calculateDiscount($promo, $subtotal, $cartItems);
            if ($d > $bestDiscount) {
                $bestDiscount = $d;
                $best         = $promo;
            }
        }

        return $best ?: false;
    }

    /**
     * Calculate discount amount for a promo against a cart.
     *
     * If applies_to_category_id is set, the discount is calculated on the
     * subtotal of items in that category only (but min_order still checks
     * against the full cart subtotal).
     *
     * @param array $cartItems  Output of CartModel::getItems() — include category_id.
     *                          Pass [] when category restriction is not needed.
     */
    public function calculateDiscount(array $promo, float $subtotal, array $cartItems = []): float
    {
        if ($subtotal < (float)$promo['min_order']) {
            return 0.0;
        }

        $base = $subtotal;

        $catId = !empty($promo['applies_to_category_id'])
            ? (int)$promo['applies_to_category_id']
            : 0;

        if ($catId > 0 && !empty($cartItems)) {
            $base = (float)array_sum(array_map(
                fn($i) => (int)$i['category_id'] === $catId
                    ? $i['quantity'] * $i['unit_price']
                    : 0.0,
                $cartItems
            ));
            if ($base <= 0.0) {
                return 0.0;
            }
        }

        $discount = $promo['discount_type'] === 'percent'
            ? $base * ((float)$promo['discount_value'] / 100)
            : (float)$promo['discount_value'];

        if (!empty($promo['max_discount'])) {
            $discount = min($discount, (float)$promo['max_discount']);
        }

        return $discount;
    }
}
