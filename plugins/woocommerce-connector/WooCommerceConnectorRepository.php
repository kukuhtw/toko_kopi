<?php

declare(strict_types=1);

use App\Config\Database;

final class WooCommerceConnectorRepository
{
    public const PLUGIN_SLUG = 'woocommerce-connector';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function ensureSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS woocommerce_sync_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                branch_id INT UNSIGNED NOT NULL,
                entity_type VARCHAR(30) NOT NULL,
                direction VARCHAR(20) NOT NULL DEFAULT "outbound",
                event_name VARCHAR(80) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT "pending",
                reference_id VARCHAR(120) DEFAULT NULL,
                payload_preview MEDIUMTEXT NULL,
                response_preview MEDIUMTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_woo_branch_created (branch_id, created_at),
                INDEX idx_woo_status (status),
                INDEX idx_woo_entity (entity_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS woocommerce_catalog_maps (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                branch_id INT UNSIGNED NOT NULL,
                menu_item_id INT UNSIGNED NOT NULL,
                variant_id INT UNSIGNED NOT NULL DEFAULT 0,
                external_product_id BIGINT UNSIGNED NOT NULL,
                external_variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                external_sku VARCHAR(120) DEFAULT NULL,
                stock_quantity DECIMAL(12,2) DEFAULT NULL,
                stock_status VARCHAR(30) DEFAULT NULL,
                external_price DECIMAL(12,2) DEFAULT NULL,
                external_payload MEDIUMTEXT NULL,
                synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_woo_catalog_external (branch_id, external_product_id, external_variation_id),
                UNIQUE KEY uq_woo_catalog_local (branch_id, menu_item_id, variant_id),
                KEY idx_woo_catalog_item (menu_item_id),
                KEY idx_woo_catalog_branch (branch_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function getBranchSetting(int $branchId, string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ?
             LIMIT 1'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $key]);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? $default : (string) $value;
    }

    public function getGlobalSetting(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare(
            'SELECT setting_val FROM app_settings
             WHERE setting_key = ?
             LIMIT 1'
        );
        $stmt->execute([$this->globalSettingKey($key)]);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? $default : (string) $value;
    }

    public function getRecentLogs(?int $branchId = null, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        if ($branchId !== null && $branchId > 0) {
            $stmt = $this->db->prepare(
                'SELECT l.*, b.name AS branch_name
                 FROM woocommerce_sync_logs l
                 LEFT JOIN branches b ON b.id = l.branch_id
                 WHERE l.branch_id = ?
                 ORDER BY l.created_at DESC, l.id DESC
                 LIMIT ?'
            );
            $stmt->bindValue(1, $branchId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare(
            'SELECT l.*, b.name AS branch_name
             FROM woocommerce_sync_logs l
             LEFT JOIN branches b ON b.id = l.branch_id
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getSummary(?int $branchId = null): array
    {
        if ($branchId !== null && $branchId > 0) {
            $stmt = $this->db->prepare(
                'SELECT
                    COUNT(*) AS total_logs,
                    SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) AS success_logs,
                    SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending_logs,
                    SUM(CASE WHEN status IN ("failed", "config_missing") THEN 1 ELSE 0 END) AS failed_logs,
                    MAX(created_at) AS last_activity
                 FROM woocommerce_sync_logs
                 WHERE branch_id = ?'
            );
            $stmt->execute([$branchId]);
            return $stmt->fetch() ?: [];
        }

        return $this->db->query(
            'SELECT
                COUNT(*) AS total_logs,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) AS success_logs,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending_logs,
                SUM(CASE WHEN status IN ("failed", "config_missing") THEN 1 ELSE 0 END) AS failed_logs,
                MAX(created_at) AS last_activity
             FROM woocommerce_sync_logs'
        )->fetch() ?: [];
    }

    public function getBranchStatuses(): array
    {
        return $this->db->query(
            'SELECT
                b.id,
                b.name,
                COALESCE(ps_active.setting_val, "0") AS is_active,
                COALESCE(ps_store.setting_val, "") AS store_url,
                COUNT(l.id) AS total_logs,
                MAX(l.created_at) AS last_activity,
                SUM(CASE WHEN l.status = "success" THEN 1 ELSE 0 END) AS success_logs,
                SUM(CASE WHEN l.status IN ("failed", "config_missing") THEN 1 ELSE 0 END) AS failed_logs
             FROM branches b
             LEFT JOIN plugin_branch_settings ps_active
               ON ps_active.branch_id = b.id
              AND ps_active.plugin_slug = "' . self::PLUGIN_SLUG . '"
              AND ps_active.setting_key = "is_active"
             LEFT JOIN plugin_branch_settings ps_store
               ON ps_store.branch_id = b.id
              AND ps_store.plugin_slug = "' . self::PLUGIN_SLUG . '"
              AND ps_store.setting_key = "store_url"
             LEFT JOIN woocommerce_sync_logs l
               ON l.branch_id = b.id
             GROUP BY b.id, b.name, ps_active.setting_val, ps_store.setting_val
             ORDER BY b.name ASC'
        )->fetchAll();
    }

    public function logSync(
        int $branchId,
        string $entityType,
        string $eventName,
        string $status,
        ?string $referenceId,
        array|string $payload = [],
        array|string $response = [],
        string $direction = 'outbound'
    ): void {
        $this->db->prepare(
            'INSERT INTO woocommerce_sync_logs
                (branch_id, entity_type, direction, event_name, status, reference_id, payload_preview, response_preview)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $branchId,
            $entityType,
            $direction,
            $eventName,
            $status,
            $referenceId,
            $this->encodePreview($payload),
            $this->encodePreview($response),
        ]);
    }

    public function findOrderByNumber(int $branchId, string $orderNumber): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM orders WHERE branch_id = ? AND order_number = ? LIMIT 1'
        );
        $stmt->execute([$branchId, $orderNumber]);
        return $stmt->fetch();
    }

    public function upsertCategory(string $name, string $slug, int $sortOrder = 0): int
    {
        $slug = $slug !== '' ? $slug : $this->slugify($name);
        $existing = $this->db->prepare('SELECT id FROM menu_categories WHERE slug = ? OR LOWER(name) = LOWER(?) LIMIT 1');
        $existing->execute([$slug, $name]);
        $id = (int)($existing->fetchColumn() ?: 0);

        if ($id > 0) {
            $this->db->prepare(
                'UPDATE menu_categories
                 SET name = ?, slug = ?, is_active = 1, sort_order = ?
                 WHERE id = ?'
            )->execute([$name, $slug, $sortOrder, $id]);
            return $id;
        }

        $this->db->prepare(
            'INSERT INTO menu_categories (name, slug, is_active, sort_order) VALUES (?, ?, 1, ?)'
        )->execute([$name, $slug, $sortOrder]);

        return (int)$this->db->lastInsertId();
    }

    public function upsertMenuItem(array $data): int
    {
        $existing = $this->db->prepare('SELECT id FROM menu_items WHERE slug = ? OR LOWER(name) = LOWER(?) LIMIT 1');
        $existing->execute([(string)$data['slug'], (string)$data['name']]);
        $id = (int)($existing->fetchColumn() ?: 0);

        if ($id > 0) {
            $stmt = $this->db->prepare(
                'UPDATE menu_items
                 SET category_id=:category_id, name=:name, slug=:slug, description=:description, price=:price,
                     min_toppings=:min_toppings, max_toppings=:max_toppings, image_path=:image_path,
                     is_available=:is_available, is_active=:is_active, sort_order=:sort_order
                 WHERE id=:id'
            );
            $stmt->execute($data + ['id' => $id]);
            return $id;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO menu_items
             (category_id, name, slug, description, price, min_toppings, max_toppings, image_path, is_available, is_active, sort_order)
             VALUES (:category_id, :name, :slug, :description, :price, :min_toppings, :max_toppings, :image_path, :is_available, :is_active, :sort_order)'
        );
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function upsertBranchMenuOverride(int $branchId, int $menuItemId, ?float $price, ?int $isAvailable, string $note = ''): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO branch_menu_overrides (branch_id, menu_item_id, custom_price, is_available, note)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE custom_price=VALUES(custom_price), is_available=VALUES(is_available), note=VALUES(note)'
        );
        $stmt->execute([$branchId, $menuItemId, $price, $isAvailable, $note !== '' ? $note : null]);
    }

    public function upsertVariant(array $data): int
    {
        $existing = $this->db->prepare(
            'SELECT id FROM menu_item_variants WHERE menu_item_id = ? AND (slug = ? OR LOWER(label) = LOWER(?)) LIMIT 1'
        );
        $existing->execute([(int)$data['menu_item_id'], (string)$data['slug'], (string)$data['label']]);
        $id = (int)($existing->fetchColumn() ?: 0);

        if ($id > 0) {
            $stmt = $this->db->prepare(
                'UPDATE menu_item_variants
                 SET label=:label, slug=:slug, price_delta=:price_delta, sort_order=:sort_order, is_active=:is_active
                 WHERE id=:id'
            );
            $updateData = $data;
            unset($updateData['menu_item_id']);
            $stmt->execute($updateData + ['id' => $id]);
            return $id;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO menu_item_variants
             (menu_item_id, label, slug, price_delta, sort_order, is_active)
             VALUES (:menu_item_id, :label, :slug, :price_delta, :sort_order, :is_active)'
        );
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function upsertCatalogMap(
        int $branchId,
        int $menuItemId,
        int $variantId,
        int $externalProductId,
        int $externalVariationId,
        ?string $externalSku,
        ?float $stockQuantity,
        ?string $stockStatus,
        ?float $externalPrice,
        array $externalPayload
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO woocommerce_catalog_maps
             (branch_id, menu_item_id, variant_id, external_product_id, external_variation_id, external_sku, stock_quantity, stock_status, external_price, external_payload, synced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                external_sku=VALUES(external_sku),
                stock_quantity=VALUES(stock_quantity),
                stock_status=VALUES(stock_status),
                external_price=VALUES(external_price),
                external_payload=VALUES(external_payload),
                synced_at=NOW()'
        );
        $stmt->execute([
            $branchId,
            $menuItemId,
            $variantId,
            $externalProductId,
            $externalVariationId,
            $externalSku,
            $stockQuantity,
            $stockStatus,
            $externalPrice,
            json_encode($externalPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function findCatalogMapByLocal(int $branchId, int $menuItemId, int $variantId = 0): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM woocommerce_catalog_maps
             WHERE branch_id = ? AND menu_item_id = ? AND variant_id = ?
             LIMIT 1'
        );
        $stmt->execute([$branchId, $menuItemId, $variantId]);
        return $stmt->fetch();
    }

    public function findCatalogMapByExternal(int $branchId, int $externalProductId, int $externalVariationId = 0): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM woocommerce_catalog_maps
             WHERE branch_id = ? AND external_product_id = ? AND external_variation_id = ?
             LIMIT 1'
        );
        $stmt->execute([$branchId, $externalProductId, $externalVariationId]);
        return $stmt->fetch();
    }

    public function getDb(): PDO
    {
        return $this->db;
    }

    private function encodePreview(array|string $value): ?string
    {
        if ($value === '' || $value === []) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    private function globalSettingKey(string $key): string
    {
        return 'plugin_' . str_replace('-', '_', self::PLUGIN_SLUG) . '_' . $key;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;
        $text = trim($text, '-');
        return $text !== '' ? $text : 'item';
    }
}
