<?php

declare(strict_types=1);

namespace App\Models;

class MenuModel extends BaseModel
{
    protected string $table = 'menu_items';

    /** Get menu with branch-level price/availability overrides */
    public function getMenuForBranch(int $branchId): array
    {
        $items = $this->query(
            'SELECT
                mi.*,
                mc.name  AS category_name,
                mc.slug  AS category_slug,
                COALESCE(bmo.custom_price, mi.price)        AS effective_price,
                COALESCE(bmo.is_available, mi.is_available) AS effective_available
             FROM menu_items mi
             JOIN menu_categories mc ON mi.category_id = mc.id
             LEFT JOIN branch_menu_overrides bmo
                  ON bmo.menu_item_id = mi.id AND bmo.branch_id = ?
             WHERE mi.is_active = 1
               AND mc.is_active = 1
             ORDER BY mc.sort_order, mi.sort_order',
            [$branchId]
        )->fetchAll();

        return $this->attachOptions($items, $branchId);
    }

    public function getCategories(): array
    {
        return $this->query(
            'SELECT * FROM menu_categories WHERE is_active = 1 ORDER BY sort_order'
        )->fetchAll();
    }

    public function getItemForBranch(int $menuItemId, int $branchId): array|false
    {
        $item = $this->query(
            'SELECT
                mi.*,
                COALESCE(bmo.custom_price, mi.price)        AS effective_price,
                COALESCE(bmo.is_available, mi.is_available) AS effective_available
             FROM menu_items mi
             LEFT JOIN branch_menu_overrides bmo
                  ON bmo.menu_item_id = mi.id AND bmo.branch_id = ?
             WHERE mi.id = ? AND mi.is_active = 1 LIMIT 1',
            [$branchId, $menuItemId]
        )->fetch();

        if (!$item) {
            return false;
        }

        return $this->attachOptions([$item], $branchId)[0] ?? false;
    }

    public function searchByName(string $query, int $branchId): array
    {
        $items = $this->query(
            'SELECT
                mi.*,
                mc.name AS category_name,
                COALESCE(bmo.custom_price, mi.price)        AS effective_price,
                COALESCE(bmo.is_available, mi.is_available) AS effective_available
             FROM menu_items mi
             JOIN menu_categories mc ON mi.category_id = mc.id
             LEFT JOIN branch_menu_overrides bmo
                  ON bmo.menu_item_id = mi.id AND bmo.branch_id = ?
             WHERE mi.is_active = 1
               AND mc.is_active = 1
               AND COALESCE(bmo.is_available, mi.is_available) = 1
               AND mi.name LIKE ?
             ORDER BY mi.name',
            [$branchId, '%' . $query . '%']
        )->fetchAll();

        return $this->attachOptions($items, $branchId);
    }

    /**
     * Flexible search for chatbot item lookup.
     * Ranks exact and near-exact matches above looser token matches.
     */
    public function searchRelevantByName(string $query, int $branchId, int $limit = 5): array
    {
        $normalizedQuery = $this->normalizeSearchText($query);
        if ($normalizedQuery === '') {
            return [];
        }

        $items = array_filter(
            $this->getMenuForBranch($branchId),
            static fn(array $item): bool => (bool)($item['effective_available'] ?? false)
        );

        $ranked = [];
        foreach ($items as $item) {
            $score = $this->scoreSearchMatch($item, $normalizedQuery);
            if ($score <= 0) {
                continue;
            }
            $item['_score'] = $score;
            $ranked[] = $item;
        }

        usort($ranked, static function (array $a, array $b): int {
            $scoreCompare = ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0);
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            $lenCompare = strlen((string)$a['name']) <=> strlen((string)$b['name']);
            if ($lenCompare !== 0) {
                return $lenCompare;
            }

            return strcmp((string)$a['name'], (string)$b['name']);
        });

        $ranked = array_slice(array_map(static function (array $item): array {
            unset($item['_score']);
            return $item;
        }, $ranked), 0, $limit);

        return $this->attachOptions($ranked, $branchId);
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->query('SELECT * FROM menu_items WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug])->fetch();
    }

    public function getMenuGrouped(int $branchId): array
    {
        $items = $this->getMenuForBranch($branchId);
        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item['category_name']][] = $item;
        }
        return $grouped;
    }

    /** Categories that have at least one available item, with item counts. */
    public function getCategoriesWithCount(int $branchId): array
    {
        return $this->query(
            'SELECT mc.id, mc.name, mc.slug, mc.sort_order,
                    COUNT(CASE WHEN mi.is_active = 1
                               AND COALESCE(bmo.is_available, mi.is_available) = 1
                          THEN 1 END) AS item_count
             FROM menu_categories mc
             LEFT JOIN menu_items mi ON mi.category_id = mc.id
             LEFT JOIN branch_menu_overrides bmo
                  ON bmo.menu_item_id = mi.id AND bmo.branch_id = ?
             WHERE mc.is_active = 1
             GROUP BY mc.id, mc.name, mc.slug, mc.sort_order
             HAVING item_count > 0
             ORDER BY mc.sort_order',
            [$branchId]
        )->fetchAll();
    }

    /** Available items in a single category, ordered by sort_order. */
    public function getMenuByCategory(int $branchId, int $categoryId, int $limit = 10, int $offset = 0): array
    {
        $items = $this->query(
            'SELECT mi.*, mc.name AS category_name, mc.slug AS category_slug,
                    COALESCE(bmo.custom_price, mi.price)        AS effective_price,
                    COALESCE(bmo.is_available, mi.is_available) AS effective_available
             FROM menu_items mi
             JOIN menu_categories mc ON mi.category_id = mc.id
             LEFT JOIN branch_menu_overrides bmo
                  ON bmo.menu_item_id = mi.id AND bmo.branch_id = ?
             WHERE mi.is_active = 1 AND mc.is_active = 1 AND mi.category_id = ?
               AND COALESCE(bmo.is_available, mi.is_available) = 1
             ORDER BY mi.sort_order
             LIMIT ? OFFSET ?',
            [$branchId, $categoryId, $limit, $offset]
        )->fetchAll();

        return $this->attachOptions($items, $branchId);
    }

    public function getVariantForItem(int $menuItemId, string $labelOrSlug): array|false
    {
        return $this->query(
            'SELECT * FROM menu_item_variants
             WHERE menu_item_id = ?
               AND is_active = 1
               AND (LOWER(label) = LOWER(?) OR LOWER(slug) = LOWER(?))
             LIMIT 1',
            [$menuItemId, $labelOrSlug, $labelOrSlug]
        )->fetch();
    }

    public function getRandomItemNotInCategories(array $excludeCategoryIds, int $branchId): array|false
    {
        $excludeSql = '';
        $params     = [$branchId];

        if (!empty($excludeCategoryIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeCategoryIds), '?'));
            $excludeSql   = "AND mi.category_id NOT IN ({$placeholders})";
            $params       = array_merge($params, $excludeCategoryIds);
        }

        return $this->query(
            "SELECT mi.*,
                    COALESCE(bmo.custom_price, mi.price)        AS effective_price,
                    COALESCE(bmo.is_available, mi.is_available) AS effective_available
             FROM menu_items mi
             LEFT JOIN branch_menu_overrides bmo
                  ON bmo.menu_item_id = mi.id AND bmo.branch_id = ?
             WHERE mi.is_active = 1
               AND COALESCE(bmo.is_available, mi.is_available) = 1
               {$excludeSql}
             ORDER BY RAND()
             LIMIT 1",
            $params
        )->fetch();
    }

    public function getRandomItemInCategories(array $includeCategoryIds, int $branchId, array $excludeMenuItemIds = []): array|false
    {
        if (empty($includeCategoryIds)) {
            return false;
        }

        $params = [$branchId];
        $includePlaceholders = implode(',', array_fill(0, count($includeCategoryIds), '?'));
        $params = array_merge($params, $includeCategoryIds);

        $excludeSql = '';
        if (!empty($excludeMenuItemIds)) {
            $excludePlaceholders = implode(',', array_fill(0, count($excludeMenuItemIds), '?'));
            $excludeSql = "AND mi.id NOT IN ({$excludePlaceholders})";
            $params = array_merge($params, $excludeMenuItemIds);
        }

        return $this->query(
            "SELECT mi.*,
                    COALESCE(bmo.custom_price, mi.price)        AS effective_price,
                    COALESCE(bmo.is_available, mi.is_available) AS effective_available
             FROM menu_items mi
             LEFT JOIN branch_menu_overrides bmo
                  ON bmo.menu_item_id = mi.id AND bmo.branch_id = ?
             WHERE mi.is_active = 1
               AND COALESCE(bmo.is_available, mi.is_available) = 1
               AND mi.category_id IN ({$includePlaceholders})
               {$excludeSql}
             ORDER BY RAND()
             LIMIT 1",
            $params
        )->fetch();
    }

    private function normalizeSearchText(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim((string)$text);
    }

    private function resolveVariant(array $v, float $basePrice, bool $isNonIdr, array $overrides): array
    {
        $variantId = (int)($v['id'] ?? 0);
        if (isset($overrides[$variantId])) {
            $effectiveDelta = $overrides[$variantId];
        } elseif ($isNonIdr) {
            $effectiveDelta = 0.0;
        } else {
            $effectiveDelta = (float)($v['price_delta'] ?? 0);
        }
        return [
            'id'           => $variantId,
            'menu_item_id' => (int)($v['menu_item_id'] ?? 0),
            'label'        => (string)($v['label'] ?? ''),
            'slug'         => (string)($v['slug'] ?? ''),
            'price_delta'  => $effectiveDelta,
            'effective_price' => round($basePrice + $effectiveDelta, 2),
        ];
    }

    private function attachOptions(array $items, int $branchId = 0): array
    {
        if (empty($items)) {
            return $items;
        }

        $menuIds = array_values(array_unique(array_map(
            static fn(array $item): int => (int)($item['id'] ?? 0),
            $items
        )));
        $menuIds = array_values(array_filter($menuIds, static fn(int $id): bool => $id > 0));

        if (empty($menuIds)) {
            return $items;
        }

        $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
        $rows = $this->query(
            "SELECT * FROM menu_item_variants
             WHERE is_active = 1 AND menu_item_id IN ({$placeholders})
             ORDER BY sort_order, id",
            $menuIds
        )->fetchAll();

        $grouped        = [];
        $allVariantIds  = [];
        foreach ($rows as $row) {
            $menuId    = (int)($row['menu_item_id'] ?? 0);
            $variantId = (int)($row['id'] ?? 0);
            $grouped[$menuId][] = $row;
            if ($variantId > 0) {
                $allVariantIds[] = $variantId;
            }
        }

        // Load per-branch variant price overrides (stored in branch's local currency)
        $variantOverrides = [];
        if ($branchId > 0 && !empty($allVariantIds)) {
            try {
                $ovPlaceholders = implode(',', array_fill(0, count($allVariantIds), '?'));
                $overrideRows   = $this->query(
                    "SELECT variant_id, price_delta
                     FROM branch_menu_variant_overrides
                     WHERE branch_id = ? AND is_active = 1 AND variant_id IN ({$ovPlaceholders})",
                    array_merge([$branchId], $allVariantIds)
                )->fetchAll();
                foreach ($overrideRows as $ov) {
                    $variantOverrides[(int)$ov['variant_id']] = (float)$ov['price_delta'];
                }
            } catch (\Throwable) {
                // Table may not exist yet — degrade gracefully
            }
        }

        $toppingRows = $this->query(
            "SELECT mit.menu_item_id, mt.*
             FROM menu_item_toppings mit
             JOIN menu_toppings mt ON mt.id = mit.topping_id
             WHERE mt.is_active = 1 AND mit.menu_item_id IN ({$placeholders})
             ORDER BY mit.sort_order, mt.sort_order, mt.id",
            $menuIds
        )->fetchAll();

        $toppingGrouped = [];
        foreach ($toppingRows as $row) {
            $menuId = (int)($row['menu_item_id'] ?? 0);
            $toppingGrouped[$menuId][] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'slug' => (string)($row['slug'] ?? ''),
                'price_delta' => (float)($row['price_delta'] ?? 0),
            ];
        }

        foreach ($items as &$item) {
            $basePrice       = (float)($item['effective_price'] ?? $item['price'] ?? 0);
            $globalBasePrice = (float)($item['price'] ?? 0);
            $isNonIdr        = $globalBasePrice > 0 && abs($basePrice - $globalBasePrice) > 0.00001;
            $menuId          = (int)($item['id'] ?? 0);
            $item['variants'] = array_map(
                fn(array $v): array => $this->resolveVariant($v, $basePrice, $isNonIdr, $variantOverrides),
                $grouped[$menuId] ?? []
            );
            $item['has_variants'] = !empty($item['variants']);
            $item['toppings']     = $toppingGrouped[$menuId] ?? [];
            $item['has_toppings'] = !empty($item['toppings']);
            $item['min_toppings'] = (int)($item['min_toppings'] ?? 0);
            $item['max_toppings'] = (int)($item['max_toppings'] ?? 0);
        }
        unset($item);

        return $items;
    }

    private function scoreSearchMatch(array $item, string $normalizedQuery): int
    {
        $name = $this->normalizeSearchText((string)($item['name'] ?? ''));
        $slug = $this->normalizeSearchText((string)($item['slug'] ?? ''));
        $desc = $this->normalizeSearchText((string)($item['description'] ?? ''));

        if ($name === '' || $normalizedQuery === '') {
            return 0;
        }

        $score = 0;

        if ($name === $normalizedQuery) {
            $score += 120;
        }
        if ($slug === str_replace(' ', '-', $normalizedQuery)) {
            $score += 100;
        }
        if (str_contains($name, $normalizedQuery)) {
            $score += 80;
        }
        if (str_starts_with($name, $normalizedQuery)) {
            $score += 20;
        }
        if ($slug !== '' && str_contains($slug, str_replace(' ', '-', $normalizedQuery))) {
            $score += 40;
        }
        if ($desc !== '' && str_contains($desc, $normalizedQuery)) {
            $score += 15;
        }

        $tokens = array_values(array_filter(explode(' ', $normalizedQuery), static fn(string $t): bool => $t !== ''));
        if (empty($tokens)) {
            return $score;
        }

        $matchedTokens = 0;
        foreach ($tokens as $token) {
            if (str_contains($name, $token)) {
                $score += 18;
                $matchedTokens++;
                continue;
            }
            if ($slug !== '' && str_contains($slug, $token)) {
                $score += 12;
                $matchedTokens++;
                continue;
            }
            if ($desc !== '' && str_contains($desc, $token)) {
                $score += 6;
                $matchedTokens++;
            }
        }

        if ($matchedTokens === count($tokens)) {
            $score += 25;
        }

        return $score;
    }
}
