<?php

declare(strict_types=1);

use KopiBot\Contracts\PluginInterface;
use KopiBot\Core\DatabaseConnection;
use KopiBot\Core\HookManager;

class WarungTemplatePlugin implements PluginInterface
{
    private const PCS     = '1 porsi';
    private const DOBEL   = 'Porsi dobel';
    private const GELAS   = '1 gelas';
    private const BOTOL   = 'Botol 600 ml';
    private const HANGAT  = 'Hangat';
    private const ES      = 'Es (dingin)';

    public function getName(): string { return 'Warung Makan Template'; }
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
                'url'   => '/dashboard/super/warung-template.php',
                'icon'  => '🍽️',
                'label' => 'Warung Template',
            ];
        }
        return $items;
    }

    public static function getCategories(): array
    {
        return [
            ['Nasi & Lauk Pauk', 'nasi-lauk',      'Menu nasi, lauk, dan sayur warung makan.',   1],
            ['Minuman Warung',   'minuman-warung',  'Minuman hangat dan dingin khas warung makan.', 2],
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
            return ['success' => false, 'message' => 'Seeding warung gagal: ' . $e->getMessage()];
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $total = array_sum(array_map('count', self::getMenuItems()));
        return ['success' => true, 'message' => "{$total} menu warung berhasil di-seed."];
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
            'nasi-lauk' => [
                ['Nasi Putih', 'nasi-putih', 'Nasi putih pulen matang sempurna, disajikan hangat.', 5000, [[self::PCS, 'porsi', 0], [self::DOBEL, 'dobel', 5000]]],
                ['Nasi Goreng Biasa', 'nasi-goreng-biasa', 'Nasi goreng bumbu bawang merah bawang putih kecap khas warung.', 15000, [[self::PCS, 'porsi', 0], [self::DOBEL, 'dobel', 8000]]],
                ['Nasi Goreng Spesial', 'nasi-goreng-spesial', 'Nasi goreng dengan tambahan telur, ayam, dan sayuran.', 20000, [[self::PCS, 'porsi', 0], [self::DOBEL, 'dobel', 10000]]],
                ['Ayam Goreng', 'ayam-goreng', 'Ayam goreng bumbu kuning renyah di luar, juicy di dalam.', 18000, [['1 potong', 'potong', 0], ['1/2 ekor', 'setengah', 15000]]],
                ['Tempe Goreng', 'tempe-goreng', 'Tempe goreng tepung garing, camilan atau lauk pelengkap nasi.', 5000, [['2 potong', 'potong2', 0], ['4 potong', 'potong4', 4000]]],
                ['Tahu Goreng', 'tahu-goreng', 'Tahu goreng kuning garing, disajikan dengan sambal kecap.', 5000, [['2 potong', 'potong2', 0], ['4 potong', 'potong4', 4000]]],
                ['Telur Dadar', 'telur-dadar', 'Telur dadar tipis gurih dengan bawang dan cabai.', 8000, [[self::PCS, 'porsi', 0], ['2 telur', 'dua', 7000]]],
                ['Sop Sayur', 'sop-sayur', 'Sop sayuran wortel, kentang, buncis dalam kuah kaldu bening.', 12000, [[self::PCS, 'porsi', 0], ['Mangkuk besar', 'besar', 5000]]],
                ['Mie Goreng', 'mie-goreng', 'Mie goreng bumbu warung dengan sayuran, telur, dan kecap manis.', 15000, [[self::PCS, 'porsi', 0], [self::DOBEL, 'dobel', 7000]]],
                ['Bakwan Sayur', 'bakwan-sayur', 'Gorengan bakwan sayur renyah, cocok untuk camilan atau lauk.', 3000, [['1 buah', 'buah', 0], ['5 buah', 'lima', 12000]]],
            ],
            'minuman-warung' => [
                ['Es Teh Manis', 'es-teh-manis', 'Teh manis segar dengan es batu, minuman paling populer warung.', 5000, [[self::GELAS, 'gelas', 0], [self::BOTOL, 'botol', 5000]]],
                ['Es Jeruk', 'es-jeruk', 'Perasan jeruk nipis segar manis dengan es, menyegarkan.', 7000, [[self::GELAS, 'gelas', 0], [self::BOTOL, 'botol', 5000]]],
                ['Teh Hangat', 'teh-hangat', 'Teh hitam manis disajikan hangat, cocok di pagi hari.', 4000, [[self::HANGAT, 'hangat', 0], [self::ES, 'dingin', 1000]]],
                ['Kopi Tubruk', 'kopi-tubruk', 'Kopi bubuk tubruk hitam khas warung, diseduh langsung.', 6000, [[self::HANGAT, 'hangat', 0], [self::ES, 'dingin', 2000]]],
                ['Indomie Rebus', 'indomie-rebus', 'Indomie kuah rasa ayam bawang atau soto, tambahan telur tersedia.', 10000, [['Tanpa telur', 'biasa', 0], ['Dengan telur', 'telor', 3000]]],
            ],
        ]);
    }
}
