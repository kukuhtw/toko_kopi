<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class RestoBasoTemplatePlugin implements PluginInterface
{
    private const MANGKUK  = 'Mangkuk biasa';
    private const BESAR    = 'Mangkuk besar';
    private const GELAS    = '1 gelas';
    private const BOTOL    = 'Botol 600 ml';
    private const HANGAT   = 'Hangat';
    private const ES       = 'Es (dingin)';
    private const PCS      = '1 porsi';

    public function getName(): string { return 'Resto Baso & Minuman Template'; }
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
                'url'   => '/dashboard/super/resto-baso-template.php',
                'icon'  => '🍜',
                'label' => 'Resto Baso Template',
            ];
        }
        return $items;
    }

    public static function getCategories(): array
    {
        return [
            ['Bakso & Mie',  'bakso-mie',      'Aneka bakso, mie, dan kuah segar khas warung baso.', 1],
            ['Camilan Baso', 'camilan-baso',    'Gorengan dan camilan pelengkap makan baso.',          2],
            ['Minuman',      'minuman-restobaso', 'Minuman segar dan hangat.',                          3],
        ];
    }

    public function resetAndSeed(): array
    {
        $pdo = Database::getInstance();
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
            return ['success' => false, 'message' => 'Seeding resto baso gagal: ' . $e->getMessage()];
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $total = array_sum(array_map('count', self::getMenuItems()));
        return ['success' => true, 'message' => "{$total} menu baso berhasil di-seed."];
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
            'bakso-mie' => [
                ['Bakso Biasa', 'bakso-biasa', 'Bola daging sapi segar dalam kuah kaldu gurih dengan mie dan bihun.', 18000, [[self::MANGKUK, 'biasa', 0], [self::BESAR, 'besar', 5000]]],
                ['Bakso Urat', 'bakso-urat', 'Bola daging sapi dengan serat urat kenyal dalam kuah panas.', 20000, [[self::MANGKUK, 'biasa', 0], [self::BESAR, 'besar', 5000]]],
                ['Bakso Telur', 'bakso-telur', 'Bola daging sapi berisi telur puyuh rebus utuh.', 22000, [[self::MANGKUK, 'biasa', 0], [self::BESAR, 'besar', 5000]]],
                ['Bakso Keju', 'bakso-keju', 'Bola daging sapi berisi keju mozzarella lumer saat digigit.', 25000, [[self::MANGKUK, 'biasa', 0], [self::BESAR, 'besar', 5000]]],
                ['Mie Bakso Spesial', 'mie-bakso-spesial', 'Mie kuning + bihun + 3 bakso urat + pangsit rebus dalam kuah kaldu spesial.', 28000, [[self::MANGKUK, 'biasa', 0], [self::BESAR, 'besar', 7000]]],
                ['Bakso Bakar', 'bakso-bakar', 'Bola daging sapi dibakar dengan bumbu kecap manis pedas manis.', 5000, [['1 tusuk (3 bola)', 'tusuk', 0], ['2 tusuk', 'dua-tusuk', 8000]]],
            ],
            'camilan-baso' => [
                ['Siomay Rebus', 'siomay-rebus', 'Siomay ikan kukus dengan bumbu kacang dan kecap.', 12000, [[self::PCS, 'porsi', 0], ['Porsi besar', 'besar', 6000]]],
                ['Pangsit Goreng', 'pangsit-goreng', 'Pangsit isi daging ayam digoreng garing, disajikan dengan saos.', 10000, [['5 buah', 'lima', 0], ['10 buah', 'sepuluh', 8000]]],
                ['Tahu Bakso', 'tahu-bakso', 'Tahu goreng diisi adonan bakso ikan segar.', 8000, [['4 buah', 'empat', 0], ['8 buah', 'delapan', 7000]]],
            ],
            'minuman-restobaso' => [
                ['Es Teh Manis', 'es-teh-manis-baso', 'Teh manis segar dengan es batu, pelengkap wajib semangkuk baso.', 5000, [[self::GELAS, 'gelas', 0], [self::BOTOL, 'botol', 5000]]],
                ['Es Jeruk', 'es-jeruk-baso', 'Perasan jeruk nipis segar manis dengan es batu.', 7000, [[self::GELAS, 'gelas', 0], [self::BOTOL, 'botol', 5000]]],
                ['Es Campur', 'es-campur', 'Es campur dengan cincau, kolang-kaling, nata de coco, dan sirup merah.', 12000, [[self::GELAS, 'gelas', 0], ['Mangkuk besar', 'besar', 5000]]],
                ['Jus Alpukat', 'jus-alpukat', 'Jus alpukat kental dengan susu kental manis dan sirup coklat.', 15000, [[self::GELAS, 'gelas', 0], ['Ukuran besar', 'besar', 5000]]],
                ['Teh Hangat', 'teh-hangat-baso', 'Teh manis hangat khas warung baso.', 4000, [[self::HANGAT, 'hangat', 0], [self::ES, 'dingin', 1000]]],
                ['Es Cincau Hitam', 'es-cincau', 'Cincau hitam segar dengan gula aren dan es batu menyegarkan.', 8000, [[self::GELAS, 'gelas', 0], ['Mangkuk besar', 'besar', 5000]]],
            ],
        ]);
    }
}
