<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class PharmacyTemplatePlugin implements PluginInterface
{
    public function getName(): string { return 'Pharmacy / Apotek Template'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string { return 'KopiBot Team'; }

    public function register(): void
    {
        HookManager::addFilter('dashboard.nav_items', [$this, 'addNavItem'], 5);
    }

    public function addNavItem(array $items, string $role): array
    {
        if ($role !== 'super_admin') {
            return $items;
        }

        if (isset($items['Settings'])) {
            $items['Settings'][] = [
                'url'   => '/dashboard/super/pharmacy-template.php',
                'icon'  => '💊',
                'label' => 'Pharmacy Template',
            ];
        }

        return $items;
    }

    public static function getCategories(): array
    {
        return [
            ['Obat Demam & Nyeri', 'obat-demam-nyeri', 'Obat penurun panas dan pereda nyeri.', 1],
            ['Alergi & Flu', 'alergi-flu', 'Obat alergi, pilek, dan flu.', 2],
            ['Antibiotik', 'antibiotik', 'Produk antibiotik umum.', 3],
            ['Lambung & Pencernaan', 'lambung-pencernaan', 'Obat lambung dan pencernaan.', 4],
            ['Batuk & Tenggorokan', 'batuk-tenggorokan', 'Obat batuk dan tenggorokan.', 5],
            ['Vitamin & Suplemen', 'vitamin-suplemen', 'Vitamin dan suplemen kesehatan.', 6],
            ['Antiseptik & Luka', 'antiseptik-luka', 'Produk antiseptik dan perawatan luka.', 7],
            ['Kulit & Alergi', 'kulit-alergi', 'Produk perawatan kulit.', 8],
            ['Penyakit Kronis', 'penyakit-kronis', 'Produk terapi penyakit kronis.', 9],
            ['Alat Kesehatan', 'alat-kesehatan', 'Peralatan kesehatan rumah tangga.', 10],
            ['Ibu & Anak', 'ibu-anak', 'Produk ibu dan anak.', 11],
            ['Kebersihan & Personal Care', 'kebersihan-personal-care', 'Produk kebersihan dan personal care.', 12],
        ];
    }

    public function resetAndSeed(): array
    {
        $pdo = Database::getInstance();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->beginTransaction();

        try {
            $pdo->exec('DELETE FROM order_status_logs');
            $pdo->exec('DELETE FROM order_items');
            $pdo->exec('DELETE FROM orders');
            $pdo->exec('DELETE FROM cart_items');
            $pdo->exec('DELETE FROM carts');
            $pdo->exec('DELETE FROM menu_item_toppings');
            $pdo->exec('DELETE FROM branch_menu_variant_overrides');
            $pdo->exec('DELETE FROM branch_menu_overrides');
            $pdo->exec('DELETE FROM menu_item_variants');
            $pdo->exec('DELETE FROM menu_items');
            $pdo->exec('DELETE FROM menu_toppings');
            $pdo->exec('DELETE FROM menu_categories');

            $stmtCat = $pdo->prepare(
                'INSERT INTO menu_categories (name, slug, description, sort_order, is_active)
                 VALUES (:name, :slug, :desc, :sort, 1)'
            );
            $stmtItem = $pdo->prepare(
                'INSERT INTO menu_items
                 (category_id, name, slug, description, price, min_toppings, max_toppings,
                  is_available, is_active, sort_order)
                 VALUES (:cat_id, :name, :slug, :desc, :price, 0, 0, 1, 1, :sort)'
            );
            $stmtVariant = $pdo->prepare(
                'INSERT INTO menu_item_variants
                 (menu_item_id, label, slug, price_delta, sort_order, is_active)
                 VALUES (:item_id, :label, :slug, :delta, :sort, 1)'
            );

            $categoryMap = [];
            foreach (self::getCategories() as [$name, $slug, $desc, $sort]) {
                $stmtCat->execute([':name' => $name, ':slug' => $slug, ':desc' => $desc, ':sort' => $sort]);
                $categoryMap[$slug] = (int) $pdo->lastInsertId();
            }

            foreach (self::getMenuItems() as $catSlug => $items) {
                $catId = $categoryMap[$catSlug] ?? null;
                if (!$catId) {
                    continue;
                }
                foreach ($items as $item) {
                    $stmtItem->execute([
                        ':cat_id' => $catId,
                        ':name'   => $item['name'],
                        ':slug'   => $item['slug'],
                        ':desc'   => $item['desc'],
                        ':price'  => $item['price'],
                        ':sort'   => $item['sort'],
                    ]);
                    $itemId = (int) $pdo->lastInsertId();
                    foreach ($item['variants'] as $vSort => [$label, $vSlug, $delta]) {
                        $stmtVariant->execute([
                            ':item_id' => $itemId,
                            ':label'   => $label,
                            ':slug'    => $itemId . '-' . $vSlug,
                            ':delta'   => $delta,
                            ':sort'    => $vSort + 1,
                        ]);
                    }
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            return ['success' => false, 'message' => 'Seeding apotek gagal: ' . $e->getMessage()];
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $total = array_sum(array_map('count', self::getMenuItems()));
        return ['success' => true, 'message' => "{$total} produk apotek berhasil di-seed."];
    }

    public static function getMenuItems(): array
    {
        $categories = [];

        foreach (self::getCategories() as $category) {
            [$name, $slug] = $category;
            $categories[$slug] = [];

            for ($i = 1; $i <= 10; $i++) {
                $categories[$slug][] = [
                    'name' => $name . ' Item ' . $i,
                    'slug' => $slug . '-item-' . $i,
                    'desc' => 'Produk kategori ' . $name . ' nomor ' . $i . '.',
                    'price' => 10000 + ($i * 2500),
                    'sort' => $i,
                    'variants' => [
                        ['Strip', 'strip', 0, 1],
                        ['Box', 'box', 25000, 2],
                    ],
                ];
            }
        }

        return $categories;
    }
}
