<?php

declare(strict_types=1);

namespace App\Models;

class CartModel extends BaseModel
{
    protected string $table = 'carts';
    private static array $columnCache = [];

    public function getOrCreate(string $sessionKey, int $branchId, int $customerId): array
    {
        $cart = $this->query(
            'SELECT * FROM carts WHERE session_key = ? LIMIT 1',
            [$sessionKey]
        )->fetch();

        if (!$cart) {
            $id = $this->insert([
                'branch_id'   => $branchId,
                'customer_id' => $customerId,
                'session_key' => $sessionKey,
                'expires_at'  => date('Y-m-d H:i:s', strtotime('+24 hours')),
            ]);
            $cart = $this->find($id);
        } elseif ((int)($cart['customer_id'] ?? 0) !== $customerId && $customerId > 0) {
            $this->query('UPDATE carts SET customer_id = ?, updated_at = NOW() WHERE id = ?', [$customerId, $cart['id']]);
            $cart = $this->find((int)$cart['id']);
        }

        return $cart;
    }

    public function getItems(int $cartId): array
    {
        return $this->query(
            'SELECT ci.*, mi.name AS base_name, mi.slug, mi.image_path,
                    CASE
                        WHEN ci.variant_label IS NOT NULL AND ci.variant_label != ""
                        THEN CONCAT(mi.name, " - ", ci.variant_label)
                        ELSE mi.name
                    END AS name,
                    mi.category_id, mc.name AS category_name
             FROM cart_items ci
             JOIN menu_items mi ON ci.menu_item_id = mi.id
             JOIN menu_categories mc ON mi.category_id = mc.id
             WHERE ci.cart_id = ?
             ORDER BY ci.id',
            [$cartId]
        )->fetchAll();
    }

    public function addItem(
        int $cartId,
        int $menuItemId,
        int $qty,
        float $unitPrice,
        string $notes = '',
        ?int $variantId = null,
        ?string $variantLabel = null
    ): int
    {
        $existing = $this->findCartItem($cartId, $menuItemId, $variantId, $notes);

        if ($existing) {
            $this->query(
                'UPDATE cart_items SET quantity = quantity + ?, unit_price = ?, notes = ?, variant_label = ? WHERE id = ?',
                [$qty, $unitPrice, $notes, $variantLabel, $existing['id']]
            );
            $itemId = $existing['id'];
        } else {
            $this->query(
                'INSERT INTO cart_items (cart_id, menu_item_id, variant_id, variant_label, quantity, unit_price, notes) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$cartId, $menuItemId, $variantId, $variantLabel, $qty, $unitPrice, $notes]
            );
            $itemId = (int) $this->db->lastInsertId();
        }
        $this->query('UPDATE carts SET updated_at = NOW() WHERE id = ?', [$cartId]);
        return $itemId;
    }

    public function updateItemNotes(int $cartItemId, string $notes): void
    {
        $this->query('UPDATE cart_items SET notes = ? WHERE id = ?', [$notes, $cartItemId]);
    }

    public function updateItem(int $cartId, int $menuItemId, int $qty, ?int $variantId = null): bool
    {
        if ($qty <= 0) {
            return $this->removeItem($cartId, $menuItemId, $variantId);
        }
        $existing = $this->findCartItem($cartId, $menuItemId, $variantId);
        if (!$existing) {
            return false;
        }
        $res = $this->query('UPDATE cart_items SET quantity = ? WHERE id = ?', [$qty, $existing['id']]);
        return $res->rowCount() > 0;
    }

    public function removeItem(int $cartId, int $menuItemId, ?int $variantId = null): bool
    {
        $existing = $this->findCartItem($cartId, $menuItemId, $variantId);
        if (!$existing) {
            return false;
        }

        return $this->query('DELETE FROM cart_items WHERE id = ?', [$existing['id']])->rowCount() > 0;
    }

    public function removeItemById(int $cartItemId): bool
    {
        return $this->query('DELETE FROM cart_items WHERE id = ?', [$cartItemId])->rowCount() > 0;
    }

    /** Return all cart_items rows for a given menu_item (any variant). */
    public function getItemsForMenu(int $cartId, int $menuItemId): array
    {
        return $this->query(
            'SELECT * FROM cart_items WHERE cart_id = ? AND menu_item_id = ? ORDER BY id',
            [$cartId, $menuItemId]
        )->fetchAll();
    }

    public function clearCart(int $cartId): void
    {
        $this->query('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
        $set = ['promo_code = NULL', 'discount_amount = 0', 'notes = NULL'];
        if ($this->hasColumn('loyalty_points_redeemed')) {
            $set[] = 'loyalty_points_redeemed = 0';
        }
        if ($this->hasColumn('loyalty_discount_amount')) {
            $set[] = 'loyalty_discount_amount = 0';
        }

        $this->query(
            'UPDATE carts SET ' . implode(', ', $set) . ' WHERE id = ?',
            [$cartId]
        );
    }

    public function getTotal(int $cartId): float
    {
        $result = $this->query(
            'SELECT SUM(quantity * unit_price) AS total FROM cart_items WHERE cart_id = ?',
            [$cartId]
        )->fetch();
        return (float) ($result['total'] ?? 0);
    }

    public function applyPromo(int $cartId, string $promoCode, float $discount): void
    {
        $totalDiscount = $discount;
        if ($this->hasColumn('loyalty_discount_amount')) {
            $carry = $this->query(
                'SELECT loyalty_discount_amount FROM carts WHERE id = ? LIMIT 1',
                [$cartId]
            )->fetchColumn();
            $totalDiscount += (float)($carry ?: 0);
        }

        $this->query(
            'UPDATE carts SET promo_code = ?, discount_amount = ? WHERE id = ?',
            [$promoCode, $totalDiscount, $cartId]
        );
    }

    public function setNotes(int $cartId, string $notes): void
    {
        $this->query('UPDATE carts SET notes = ? WHERE id = ?', [$notes, $cartId]);
    }

    public function getBySession(string $sessionKey): array|false
    {
        return $this->query(
            'SELECT * FROM carts WHERE session_key = ? LIMIT 1',
            [$sessionKey]
        )->fetch();
    }

    private function findCartItem(int $cartId, int $menuItemId, ?int $variantId = null, string $notes = ''): array|false
    {
        if ($variantId === null) {
            return $this->query(
                'SELECT * FROM cart_items
                 WHERE cart_id = ? AND menu_item_id = ? AND variant_id IS NULL AND COALESCE(notes, "") = ?
                 LIMIT 1',
                [$cartId, $menuItemId, $notes]
            )->fetch();
        }

        return $this->query(
            'SELECT * FROM cart_items
             WHERE cart_id = ? AND menu_item_id = ? AND variant_id = ? AND COALESCE(notes, "") = ?
             LIMIT 1',
            [$cartId, $menuItemId, $variantId, $notes]
        )->fetch();
    }

    private function hasColumn(string $column): bool
    {
        $key = $this->table . '.' . $column;
        if (array_key_exists($key, self::$columnCache)) {
            return self::$columnCache[$key];
        }

        $stmt = $this->query('SHOW COLUMNS FROM ' . $this->table . ' LIKE ?', [$column]);
        self::$columnCache[$key] = (bool) $stmt->fetch();

        return self::$columnCache[$key];
    }
}
