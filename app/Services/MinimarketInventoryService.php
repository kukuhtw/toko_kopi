<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class MinimarketInventoryService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function stockIn(array $payload): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO minimarket_inventory_stock
            (branch_id, menu_item_id, sku, barcode, batch_no, expired_date, qty, purchase_price, selling_price, supplier_name, created_at, updated_at)
            VALUES
            (:branch_id, :menu_item_id, :sku, :barcode, :batch_no, :expired_date, :qty, :purchase_price, :selling_price, :supplier_name, NOW(), NOW())'
        );

        $stmt->execute([
            ':branch_id' => $payload['branch_id'] ?? 1,
            ':menu_item_id' => $payload['menu_item_id'],
            ':sku' => $payload['sku'] ?? null,
            ':barcode' => $payload['barcode'] ?? null,
            ':batch_no' => $payload['batch_no'] ?? null,
            ':expired_date' => $payload['expired_date'] ?? null,
            ':qty' => $payload['qty'],
            ':purchase_price' => $payload['purchase_price'] ?? 0,
            ':selling_price' => $payload['selling_price'] ?? 0,
            ':supplier_name' => $payload['supplier_name'] ?? null,
        ]);

        $stockId = (int)$this->pdo->lastInsertId();

        $this->movement(
            $payload['branch_id'] ?? 1,
            $payload['menu_item_id'],
            $stockId,
            'stock_in',
            (float)$payload['qty'],
            null,
            null,
            'Stock in'
        );

        return $stockId;
    }

    public function deductFifo(int $branchId, int $menuItemId, float $qty): void
    {
        $remaining = $qty;

        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM minimarket_inventory_stock
             WHERE branch_id = :branch_id
               AND menu_item_id = :menu_item_id
               AND qty > 0
             ORDER BY expired_date ASC, id ASC'
        );

        $stmt->execute([
            ':branch_id' => $branchId,
            ':menu_item_id' => $menuItemId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float)$row['qty'];
            $deduct = min($available, $remaining);

            $update = $this->pdo->prepare(
                'UPDATE minimarket_inventory_stock
                 SET qty = qty - :qty,
                     updated_at = NOW()
                 WHERE id = :id'
            );

            $update->execute([
                ':qty' => $deduct,
                ':id' => $row['id'],
            ]);

            $this->movement(
                $branchId,
                $menuItemId,
                (int)$row['id'],
                'sale',
                -1 * $deduct,
                null,
                null,
                'POS sale deduction'
            );

            $remaining -= $deduct;
        }

        if ($remaining > 0) {
            throw new RuntimeException('Insufficient minimarket stock.');
        }
    }

    public function movement(
        int $branchId,
        int $menuItemId,
        ?int $stockId,
        string $movementType,
        float $qty,
        ?string $referenceType,
        ?int $referenceId,
        ?string $notes
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO minimarket_stock_movements
            (branch_id, menu_item_id, stock_id, movement_type, qty, reference_type, reference_id, notes, created_at)
            VALUES
            (:branch_id, :menu_item_id, :stock_id, :movement_type, :qty, :reference_type, :reference_id, :notes, NOW())'
        );

        $stmt->execute([
            ':branch_id' => $branchId,
            ':menu_item_id' => $menuItemId,
            ':stock_id' => $stockId,
            ':movement_type' => $movementType,
            ':qty' => $qty,
            ':reference_type' => $referenceType,
            ':reference_id' => $referenceId,
            ':notes' => $notes,
        ]);
    }
}
