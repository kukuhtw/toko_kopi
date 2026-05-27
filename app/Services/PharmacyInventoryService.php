<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use PDO;
use RuntimeException;

class PharmacyInventoryService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getInstance();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getDashboardSummary(?int $branchId = null): array
    {
        $branchSql = $branchId ? ' WHERE branch_id = :branch_id ' : '';
        $params = $branchId ? [':branch_id' => $branchId] : [];

        $total = $this->scalar('SELECT COUNT(*) FROM pharmacy_inventory_stock' . $branchSql, $params);
        $low = $this->scalar('SELECT COUNT(*) FROM pharmacy_inventory_stock' . ($branchId ? ' WHERE branch_id = :branch_id AND ' : ' WHERE ') . 'stock_qty <= minimum_stock_qty', $params);
        $expiredSoon = $this->scalar('SELECT COUNT(*) FROM pharmacy_inventory_stock' . ($branchId ? ' WHERE branch_id = :branch_id AND ' : ' WHERE ') . 'expired_date IS NOT NULL AND expired_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)', $params);
        $expired = $this->scalar('SELECT COUNT(*) FROM pharmacy_inventory_stock' . ($branchId ? ' WHERE branch_id = :branch_id AND ' : ' WHERE ') . 'expired_date IS NOT NULL AND expired_date < CURDATE()', $params);

        return [
            'total_stock_rows' => (int)$total,
            'low_stock_rows' => (int)$low,
            'expired_soon_rows' => (int)$expiredSoon,
            'expired_rows' => (int)$expired,
        ];
    }

    public function searchStock(?int $branchId = null, string $keyword = '', int $limit = 100): array
    {
        $where = [];
        $params = [];

        if ($branchId) {
            $where[] = 's.branch_id = :branch_id';
            $params[':branch_id'] = $branchId;
        }

        if ($keyword !== '') {
            $where[] = '(s.sku LIKE :keyword OR s.batch_no LIKE :keyword OR mi.name LIKE :keyword OR pm.generic_name LIKE :keyword OR pm.bpom_no LIKE :keyword)';
            $params[':keyword'] = '%' . $keyword . '%';
        }

        $sql = "SELECT s.*, mi.name AS product_name, mv.label AS variant_label, pm.generic_name, pm.bpom_no, pm.requires_prescription
                FROM pharmacy_inventory_stock s
                JOIN menu_items mi ON mi.id = s.menu_item_id
                LEFT JOIN menu_item_variants mv ON mv.id = s.variant_id
                LEFT JOIN pharmacy_product_metadata pm ON pm.menu_item_id = mi.id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY s.expired_date IS NULL, s.expired_date ASC, mi.name ASC LIMIT ' . max(1, min($limit, 500));

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addStock(array $data, ?int $userId = null): int
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO pharmacy_inventory_stock
                 (branch_id, menu_item_id, variant_id, sku, batch_no, expired_date, stock_qty, minimum_stock_qty, unit, rack_location, is_active)
                 VALUES (:branch_id, :menu_item_id, :variant_id, :sku, :batch_no, :expired_date, :stock_qty, :minimum_stock_qty, :unit, :rack_location, 1)'
            );

            $stmt->execute([
                ':branch_id' => (int)$data['branch_id'],
                ':menu_item_id' => (int)$data['menu_item_id'],
                ':variant_id' => $data['variant_id'] ?? null,
                ':sku' => (string)$data['sku'],
                ':batch_no' => $data['batch_no'] ?? null,
                ':expired_date' => $data['expired_date'] ?? null,
                ':stock_qty' => (float)$data['stock_qty'],
                ':minimum_stock_qty' => (float)($data['minimum_stock_qty'] ?? 5),
                ':unit' => (string)($data['unit'] ?? 'pcs'),
                ':rack_location' => $data['rack_location'] ?? null,
            ]);

            $stockId = (int)$this->pdo->lastInsertId();

            $this->recordMovement($stockId, (int)$data['branch_id'], (int)$data['menu_item_id'], 'in', (float)$data['stock_qty'], 0, (float)$data['stock_qty'], 'initial_stock', null, 'Initial stock input', $userId);

            $this->pdo->commit();
            return $stockId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function deductFifo(int $branchId, int $menuItemId, float $qty, string $referenceType = 'sale', ?int $referenceId = null, ?int $userId = null): array
    {
        if ($qty <= 0) {
            throw new RuntimeException('Qty must be greater than zero.');
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM pharmacy_inventory_stock
                 WHERE branch_id = :branch_id
                   AND menu_item_id = :menu_item_id
                   AND stock_qty > 0
                   AND is_active = 1
                   AND (expired_date IS NULL OR expired_date >= CURDATE())
                 ORDER BY expired_date IS NULL, expired_date ASC, id ASC
                 FOR UPDATE"
            );
            $stmt->execute([':branch_id' => $branchId, ':menu_item_id' => $menuItemId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $available = array_sum(array_map(fn($r) => (float)$r['stock_qty'], $rows));
            if ($available < $qty) {
                throw new RuntimeException('Insufficient stock. Available: ' . $available . ', requested: ' . $qty);
            }

            $remaining = $qty;
            $deductions = [];

            foreach ($rows as $row) {
                if ($remaining <= 0) {
                    break;
                }

                $before = (float)$row['stock_qty'];
                $take = min($before, $remaining);
                $after = $before - $take;

                $update = $this->pdo->prepare('UPDATE pharmacy_inventory_stock SET stock_qty = :after_qty WHERE id = :id');
                $update->execute([':after_qty' => $after, ':id' => (int)$row['id']]);

                $this->recordMovement((int)$row['id'], $branchId, $menuItemId, 'sale', $take, $before, $after, $referenceType, $referenceId, 'FIFO stock deduction', $userId);

                $deductions[] = [
                    'stock_id' => (int)$row['id'],
                    'sku' => $row['sku'],
                    'batch_no' => $row['batch_no'],
                    'expired_date' => $row['expired_date'],
                    'qty' => $take,
                ];

                $remaining -= $take;
            }

            $this->pdo->commit();
            return $deductions;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function recordMovement(int $stockId, int $branchId, int $menuItemId, string $type, float $qty, float $before, float $after, ?string $referenceType, ?int $referenceId, ?string $note, ?int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pharmacy_stock_movements
             (stock_id, branch_id, menu_item_id, movement_type, qty, before_qty, after_qty, reference_type, reference_id, note, created_by)
             VALUES (:stock_id, :branch_id, :menu_item_id, :movement_type, :qty, :before_qty, :after_qty, :reference_type, :reference_id, :note, :created_by)'
        );

        $stmt->execute([
            ':stock_id' => $stockId,
            ':branch_id' => $branchId,
            ':menu_item_id' => $menuItemId,
            ':movement_type' => $type,
            ':qty' => $qty,
            ':before_qty' => $before,
            ':after_qty' => $after,
            ':reference_type' => $referenceType,
            ':reference_id' => $referenceId,
            ':note' => $note,
            ':created_by' => $userId,
        ]);
    }

    private function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
