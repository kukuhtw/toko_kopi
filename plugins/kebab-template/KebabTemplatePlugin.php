<?php

declare(strict_types=1);

use KopiBot\Contracts\PluginInterface;
use KopiBot\Core\DatabaseConnection;
use KopiBot\Core\HookManager;

class KebabTemplatePlugin implements PluginInterface
{
    private const PCS    = '1 porsi';
    private const MINI   = 'Mini (kecil)';
    private const JUMBO  = 'Jumbo (besar)';
    private const GELAS  = '1 gelas';
    private const BOTOL  = 'Botol 600 ml';
    private const HANGAT = 'Hangat';
    private const ES     = 'Es (dingin)';

    public function getName(): string { return 'Kebab Template'; }
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
                'url'   => '/dashboard/super/kebab-template.php',
                'icon'  => '🌯',
                'label' => 'Kebab Template',
            ];
        }
        return $items;
    }

    public static function getCategories(): array
    {
        return [
            ['Kebab & Wrap',      'kebab-wrap',     'Aneka kebab daging, ayam, dan vegetarian dalam roti pita.',  1],
            ['Minuman & Pelengkap', 'minuman-kebab', 'Minuman segar dan pelengkap menu kebab.',                   2],
        ];
    }

    public function resetAndSeed(): array
    {
        $pdo = DatabaseConnection::getInstance();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->beginTransaction();

        try {
            foreach ([
                'order_status_logs', 'order_items', 'orders',
                'cart_items', 'carts', 'menu_item_toppings',
                'branch_menu_variant_overrides', 'branch_menu_overrides',
                'menu_item_variants', 'menu_items', 'menu_toppings', 'menu_categories',
            ] as $table) {
                $pdo->exec("DELETE FROM {$table}");
            }

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
            return ['success' => false, 'message' => 'Seeding kebab gagal: ' . $e->getMessage()];
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $total = array_sum(array_map('count', self::getMenuItems()));
        return ['success' => true, 'message' => "{$total} menu kebab berhasil di-seed."];
    }

    private static function buildItems(array $raw): array
    {
        $result = [];
        foreach ($raw as $catSlug => $items) {
            $result[$catSlug] = [];
            foreach ($items as $sort => [$name, $slug, $desc, $price, $variants]) {
                $result[$catSlug][] = [
                    'name'     => $name,
                    'slug'     => $slug,
                    'desc'     => $desc,
                    'price'    => $price,
                    'sort'     => $sort + 1,
                    'variants' => $variants,
                ];
            }
        }
        return $result;
    }

    public static function getMenuItems(): array
    {
        return self::buildItems([
            'kebab-wrap' => [
                ['Kebab Original', 'kebab-original', 'Kebab daging sapi cincang bumbu rempah khas Turki dalam roti pita panggang, dengan saus tomat dan mayones.', 18000, [[self::MINI, 'mini', -3000], [self::PCS, 'reguler', 0], [self::JUMBO, 'jumbo', 7000]]],
                ['Kebab Ayam Crispy', 'kebab-ayam-crispy', 'Kebab isi potongan ayam goreng tepung renyah dengan saus ranch dan sayuran segar.', 20000, [[self::MINI, 'mini', -3000], [self::PCS, 'reguler', 0], [self::JUMBO, 'jumbo', 7000]]],
                ['Kebab Mozarella', 'kebab-mozarella', 'Kebab daging sapi dengan lelehan keju mozarella, saus BBQ, dan bawang karamel.', 25000, [[self::PCS, 'reguler', 0], [self::JUMBO, 'jumbo', 8000]]],
                ['Kebab Double Beef', 'kebab-double-beef', 'Kebab dua lapis daging sapi panggang dengan ekstra saus dan sayuran pilihan.', 28000, [[self::PCS, 'reguler', 0], [self::JUMBO, 'jumbo', 10000]]],
                ['Kebab Spesial Pedas', 'kebab-spesial-pedas', 'Kebab daging sapi dengan sambal cabai khas, jalapeno, dan saus sriracha pedas menggigit.', 22000, [[self::MINI, 'mini', -3000], [self::PCS, 'reguler', 0], [self::JUMBO, 'jumbo', 8000]]],
                ['Kebab Smoked Beef', 'kebab-smoked-beef', 'Kebab isi smoked beef daging asap dengan saus mustard, acar mentimun, dan selada.', 24000, [[self::PCS, 'reguler', 0], [self::JUMBO, 'jumbo', 8000]]],
                ['Kebab Veggie', 'kebab-veggie', 'Kebab isi campuran sayuran panggang, jamur, paprika, zucchini, dengan hummus dan saus yogurt.', 18000, [[self::MINI, 'mini', -3000], [self::PCS, 'reguler', 0], [self::JUMBO, 'jumbo', 6000]]],
                ['Shawarma Ayam', 'shawarma-ayam', 'Shawarma ayam bumbu rempah Timur Tengah dengan bawang putih toum, timun, dan tomat.', 22000, [[self::PCS, 'reguler', 0], [self::JUMBO, 'jumbo', 8000]]],
                ['Pita Wrap Falafel', 'pita-wrap-falafel', 'Pita wrap isi bola falafel crispy dari kacang arab dengan saus tahini dan salad.', 20000, [[self::PCS, 'reguler', 0], [self::JUMBO, 'jumbo', 7000]]],
                ['Kebab Box Komplit', 'kebab-box', 'Paket komplit: 1 kebab reguler + kentang goreng + minuman pilihan.', 35000, [['Paket Hemat', 'hemat', 0], ['Paket Jumbo', 'jumbo', 10000]]],
            ],
            'minuman-kebab' => [
                ['Es Teh Manis', 'es-teh-manis-kebab', 'Teh manis dingin segar, pasangan sempurna kebab.', 5000, [[self::GELAS, 'gelas', 0], [self::BOTOL, 'botol', 5000]]],
                ['Es Jeruk Segar', 'es-jeruk-kebab', 'Perasan jeruk nipis segar dengan es batu dan sedikit gula.', 8000, [[self::GELAS, 'gelas', 0], [self::BOTOL, 'botol', 5000]]],
                ['Milkshake Coklat', 'milkshake-coklat', 'Milkshake tebal dan creamy rasa coklat dengan whipped cream.', 18000, [['Regular 300 ml', 'reguler', 0], ['Large 500 ml', 'large', 7000]]],
                ['Milkshake Vanilla', 'milkshake-vanilla', 'Milkshake lembut rasa vanilla dengan taburan coklat bubuk.', 18000, [['Regular 300 ml', 'reguler', 0], ['Large 500 ml', 'large', 7000]]],
                ['Air Mineral', 'air-mineral-kebab', 'Air mineral dingin kemasan botol.', 5000, [['Botol 330 ml', 'kecil', 0], [self::BOTOL, 'besar', 3000]]],
            ],
        ]);
    }
}
