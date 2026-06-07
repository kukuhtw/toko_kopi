<?php

declare(strict_types=1);

use KopiBot\Contracts\PluginInterface;
use KopiBot\Core\DatabaseConnection;
use KopiBot\Core\HookManager;

class BurgerTemplatePlugin implements PluginInterface
{
    private const PCS    = '1 porsi';
    private const SINGLE = 'Single patty';
    private const DOUBLE = 'Double patty';
    private const REG    = 'Regular';
    private const LARGE  = 'Large';
    private const GELAS  = '1 gelas';
    private const BOTOL  = 'Botol 600 ml';

    public function getName(): string { return 'Burger Template'; }
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
                'url'   => '/dashboard/super/burger-template.php',
                'icon'  => '🍔',
                'label' => 'Burger Template',
            ];
        }
        return $items;
    }

    public static function getCategories(): array
    {
        return [
            ['Burger',           'burger',         'Aneka burger daging sapi, ayam, dan pilihan lainnya.',     1],
            ['Sides & Snack',    'sides-snack',    'Kentang goreng, onion ring, dan pelengkap burger.',        2],
            ['Minuman',          'minuman-burger',  'Minuman segar dan milkshake pelengkap burger.',            3],
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
            return ['success' => false, 'message' => 'Seeding burger gagal: ' . $e->getMessage()];
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $total = array_sum(array_map('count', self::getMenuItems()));
        return ['success' => true, 'message' => "{$total} menu burger berhasil di-seed."];
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
            'burger' => [
                ['Burger Classic Beef', 'burger-classic-beef', 'Burger daging sapi 100% dengan selada, tomat, acar, bawang bombay, saus tomat, dan mustard dalam roti sesame bun.', 25000, [[self::SINGLE, 'single', 0], [self::DOUBLE, 'double', 10000]]],
                ['Burger Ayam Crispy', 'burger-ayam-crispy', 'Burger fillet ayam goreng tepung renyah dengan selada, tomat, dan saus honey mustard.', 22000, [[self::SINGLE, 'single', 0], [self::DOUBLE, 'double', 10000]]],
                ['Burger BBQ Smoky', 'burger-bbq-smoky', 'Burger daging sapi dengan saus BBQ asap, bawang karamel, dan cheddar slice.', 28000, [[self::SINGLE, 'single', 0], [self::DOUBLE, 'double', 10000]]],
                ['Burger Mushroom Swiss', 'burger-mushroom-swiss', 'Burger daging sapi dengan tumisan jamur, keju Swiss meleleh, dan saus creamy.', 30000, [[self::SINGLE, 'single', 0], [self::DOUBLE, 'double', 10000]]],
                ['Burger Mozarella Melt', 'burger-mozarella-melt', 'Burger daging sapi dengan lelehan keju mozarella, saus tomat segar, dan basil.', 32000, [[self::SINGLE, 'single', 0], [self::DOUBLE, 'double', 12000]]],
                ['Burger Spicy Crunch', 'burger-spicy-crunch', 'Burger ayam crispy pedas dengan saus sriracha, jalapeno, dan coleslaw creamy.', 25000, [[self::SINGLE, 'single', 0], [self::DOUBLE, 'double', 10000]]],
                ['Burger Fish Fillet', 'burger-fish-fillet', 'Burger fillet ikan dori goreng tepung dengan saus tartar, selada, dan lemon.', 25000, [[self::PCS, 'porsi', 0], ['Ekstra saus', 'ekstra-saus', 3000]]],
                ['Burger Veggie Patty', 'burger-veggie', 'Burger patty sayuran dan kacang-kacangan dengan saus pesto dan sayuran segar.', 22000, [[self::SINGLE, 'single', 0], [self::DOUBLE, 'double', 10000]]],
                ['Burger Box Paket', 'burger-box-paket', 'Paket komplit: 1 burger pilihan + french fries regular + minuman ukuran reguler.', 40000, [['Paket Hemat', 'hemat', 0], ['Paket Jumbo + Fries Large', 'jumbo', 15000]]],
            ],
            'sides-snack' => [
                ['French Fries', 'french-fries', 'Kentang goreng crispy renyah dengan garam dan seasoning khas.', 12000, [[self::REG, 'reguler', 0], [self::LARGE, 'large', 5000]]],
                ['Onion Ring', 'onion-ring', 'Cincin bawang bombay berbalut tepung renyah, disajikan dengan saus ranch.', 15000, [[self::REG, 'reguler', 0], [self::LARGE, 'large', 5000]]],
                ['Chicken Wings', 'chicken-wings', 'Sayap ayam goreng crispy dengan pilihan saus BBQ, buffalo, atau original.', 22000, [['4 pcs', 'empat', 0], ['8 pcs', 'delapan', 18000]]],
            ],
            'minuman-burger' => [
                ['Es Teh Manis', 'es-teh-manis-burger', 'Teh manis dingin segar pelengkap wajib burger.', 5000, [[self::GELAS, 'gelas', 0], [self::BOTOL, 'botol', 5000]]],
                ['Soft Drink Cola', 'soft-drink-cola', 'Minuman soda cola segar dengan es batu dalam gelas besar.', 8000, [[self::REG, 'reguler', 0], [self::LARGE, 'large', 4000]]],
                ['Milkshake Vanilla', 'milkshake-vanilla-burger', 'Milkshake tebal creamy rasa vanilla klasik dengan whipped cream.', 20000, [[self::REG, 'reguler', 0], [self::LARGE, 'large', 7000]]],
                ['Milkshake Coklat', 'milkshake-coklat-burger', 'Milkshake coklat kental dengan es krim vanilla dan taburan coklat.', 20000, [[self::REG, 'reguler', 0], [self::LARGE, 'large', 7000]]],
            ],
        ]);
    }
}
