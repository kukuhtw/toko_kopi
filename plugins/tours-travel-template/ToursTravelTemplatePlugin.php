<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class ToursTravelTemplatePlugin implements PluginInterface
{
    private const SHARED = 'Twin Share';
    private const TRIPLE = 'Triple Share';
    private const PRIVATE = 'Private Tour';
    private const STANDARD = 'Standard';
    private const PREMIUM = 'Premium';
    private const GROUP = 'Group 20 pax';
    private const FAMILY = 'Family Package';

    public function getName(): string { return 'Tours & Travel Template'; }
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
                'url'   => '/dashboard/super/tours-travel-template.php',
                'icon'  => '✈️',
                'label' => 'Tours & Travel Template',
            ];
        }
        return $items;
    }

    public static function getCategories(): array
    {
        return [
            ['Paket Wisata Domestik', 'paket-domestik', 'Paket liburan dalam negeri lengkap dengan hotel, transport, dan itinerary.', 1],
            ['Paket Wisata Internasional', 'paket-internasional', 'Paket tour luar negeri untuk family trip, honeymoon, dan group departure.', 2],
            ['Layanan Tambahan Travel', 'layanan-travel', 'Add-on penting seperti visa, asuransi, transfer, dan upgrade layanan.', 3],
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
            return ['success' => false, 'message' => 'Seeding tours & travel gagal: ' . $e->getMessage()];
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $total = array_sum(array_map('count', self::getMenuItems()));
        return ['success' => true, 'message' => "{$total} layanan tours & travel berhasil di-seed."];
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
            'paket-domestik' => [
                ['Bali 3D2N Leisure', 'bali-3d2n', 'Paket wisata Bali 3 hari 2 malam termasuk hotel, city tour, dan transport lokal.', 2850000, [[self::SHARED, 'twin', 0], [self::PRIVATE, 'private', 850000]]],
                ['Jogja Heritage 3D2N', 'jogja-heritage-3d2n', 'Wisata budaya Jogja lengkap dengan Malioboro, candi, dan jeep Merapi.', 2350000, [[self::SHARED, 'twin', 0], [self::FAMILY, 'family', 1200000]]],
                ['Labuan Bajo Sailing 3D2N', 'labuan-bajo-sailing', 'Trip Komodo, Padar, Pink Beach, dan kapal liveaboard.', 4250000, [[self::GROUP, 'group', 0], [self::PRIVATE, 'private', 1750000]]],
                ['Bromo Ijen Adventure 3D2N', 'bromo-ijen-3d2n', 'Paket sunrise Bromo dan blue fire Ijen untuk pencinta petualangan.', 2650000, [[self::SHARED, 'twin', 0], [self::PRIVATE, 'private', 700000]]],
                ['Lombok Gili Escape 4D3N', 'lombok-gili-4d3n', 'Stay di Senggigi dan island hopping ke Gili Trawangan, Meno, Air.', 3550000, [[self::SHARED, 'twin', 0], [self::FAMILY, 'family', 1450000]]],
            ],
            'paket-internasional' => [
                ['Singapore Family 3D2N', 'singapore-family-3d2n', 'Universal Studios, Marina Bay, Merlion, hotel, dan city transport.', 5950000, [[self::STANDARD, 'standard', 0], [self::PREMIUM, 'premium', 1650000]]],
                ['Kuala Lumpur Genting 4D3N', 'kl-genting-4d3n', 'Paket city tour KL, Batu Caves, Genting Highlands, dan shopping.', 5450000, [[self::STANDARD, 'standard', 0], [self::PREMIUM, 'premium', 1450000]]],
                ['Bangkok Pattaya 4D3N', 'bangkok-pattaya-4d3n', 'Highlight Thailand untuk leisure, kuliner, dan belanja.', 6150000, [[self::STANDARD, 'standard', 0], [self::PREMIUM, 'premium', 1550000]]],
                ['Japan Sakura 6D5N', 'japan-sakura-6d5n', 'Tokyo, Fuji, Osaka, dan Kyoto saat musim sakura dengan guide Indonesia.', 16850000, [[self::STANDARD, 'standard', 0], [self::PREMIUM, 'premium', 3500000]]],
                ['Turki Cappadocia 7D6N', 'turki-cappadocia-7d6n', 'Istanbul, Bursa, Pamukkale, dan Cappadocia untuk first timer.', 18950000, [[self::STANDARD, 'standard', 0], [self::PREMIUM, 'premium', 4200000]]],
            ],
            'layanan-travel' => [
                ['Asuransi Perjalanan Internasional', 'asuransi-perjalanan', 'Proteksi medis, delay, dan bagasi hilang untuk perjalanan luar negeri.', 350000, [[self::STANDARD, 'standard', 0], [self::PREMIUM, 'premium', 225000]]],
                ['Airport Transfer Private', 'airport-transfer-private', 'Mobil private dari/ke bandara untuk 1-4 penumpang.', 450000, [['Sekali jalan', 'oneway', 0], ['Pulang pergi', 'roundtrip', 300000]]],
                ['Pengurusan Visa Wisata', 'pengurusan-visa', 'Jasa pengurusan dokumen dan appointment visa untuk destinasi tertentu.', 750000, [['Single Entry', 'single', 0], ['Priority Service', 'priority', 450000]]],
                ['Upgrade Hotel Paket', 'upgrade-hotel-paket', 'Upgrade hotel dari bintang 3 ke 4/5 sesuai paket tour.', 950000, [['Upgrade 4 Star', 'up4', 0], ['Upgrade 5 Star', 'up5', 950000]]],
                ['Private Guide Bahasa Indonesia', 'private-guide-id', 'Pendamping wisata privat dengan itinerary fleksibel.', 1250000, [['Half Day', 'halfday', 0], ['Full Day', 'fullday', 850000]]],
            ],
        ]);
    }
}
