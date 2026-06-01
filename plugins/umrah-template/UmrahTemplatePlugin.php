<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class UmrahTemplatePlugin implements PluginInterface
{
    private const QUAD = 'Quad Room';
    private const TRIPLE = 'Triple Room';
    private const DOUBLE = 'Double Room';
    private const REGULAR = 'Reguler';
    private const VIP = 'VIP';
    private const BASIC = 'Basic';
    private const FULL = 'Full Set';

    public function getName(): string { return 'Umrah Template'; }
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
                'url'   => '/dashboard/super/umrah-template.php',
                'icon'  => '🕋',
                'label' => 'Umrah Template',
            ];
        }
        return $items;
    }

    public static function getCategories(): array
    {
        return [
            ['Paket Umrah Reguler', 'paket-umrah-reguler', 'Paket umrah hemat dan reguler dengan hotel nyaman dan jadwal favorit.', 1],
            ['Paket Umrah Premium', 'paket-umrah-premium', 'Paket umrah eksklusif dengan hotel dekat masjid dan layanan prioritas.', 2],
            ['Layanan & Perlengkapan Umrah', 'layanan-perlengkapan-umrah', 'Dokumen, handling, perlengkapan, dan add-on pendukung jamaah.', 3],
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
            return ['success' => false, 'message' => 'Seeding umrah gagal: ' . $e->getMessage()];
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $total = array_sum(array_map('count', self::getMenuItems()));
        return ['success' => true, 'message' => "{$total} layanan umrah berhasil di-seed."];
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
            'paket-umrah-reguler' => [
                ['Umrah Reguler 9 Hari', 'umrah-reguler-9-hari', 'Paket umrah 9 hari dengan city tour Madinah dan Makkah.', 28900000, [[self::QUAD, 'quad', 0], [self::TRIPLE, 'triple', 2500000], [self::DOUBLE, 'double', 5200000]]],
                ['Umrah Reguler 12 Hari', 'umrah-reguler-12-hari', 'Paket 12 hari dengan itinerary santai dan pendamping ibadah lengkap.', 31900000, [[self::QUAD, 'quad', 0], [self::TRIPLE, 'triple', 2800000], [self::DOUBLE, 'double', 5800000]]],
                ['Umrah Ramadhan 15 Hari', 'umrah-ramadhan-15-hari', 'Paket spesial Ramadhan untuk target ibadah maksimal di tanah suci.', 39500000, [[self::QUAD, 'quad', 0], [self::TRIPLE, 'triple', 3200000], [self::DOUBLE, 'double', 6900000]]],
                ['Umrah Akhir Tahun', 'umrah-akhir-tahun', 'Cocok untuk keluarga yang ingin berangkat saat libur sekolah dan akhir tahun.', 33800000, [[self::QUAD, 'quad', 0], [self::TRIPLE, 'triple', 2500000], [self::DOUBLE, 'double', 5600000]]],
                ['Umrah Plus Thaif', 'umrah-plus-thaif', 'Paket umrah dengan tambahan city tour dan ziarah ke Thaif.', 35200000, [[self::QUAD, 'quad', 0], [self::TRIPLE, 'triple', 2800000], [self::DOUBLE, 'double', 5900000]]],
            ],
            'paket-umrah-premium' => [
                ['Umrah VIP 9 Hari Hotel Dekat Haram', 'umrah-vip-9-hari', 'Hotel premium dekat Masjidil Haram dan Nabawi dengan fasilitas eksklusif.', 45900000, [[self::DOUBLE, 'double', 0], [self::TRIPLE, 'triple', -2500000]]],
                ['Umrah Plus Turki 13 Hari', 'umrah-plus-turki', 'Gabungan ibadah umrah dan wisata Turki untuk pengalaman spiritual dan historis.', 55900000, [[self::DOUBLE, 'double', 0], [self::TRIPLE, 'triple', -2800000]]],
                ['Umrah Plus Dubai 12 Hari', 'umrah-plus-dubai', 'Program umrah premium dengan stopover dan city tour Dubai.', 52500000, [[self::DOUBLE, 'double', 0], [self::TRIPLE, 'triple', -2400000]]],
                ['Umrah Family Private', 'umrah-family-private', 'Layanan private handling untuk keluarga besar dengan jadwal fleksibel.', 48900000, [[self::FAMILY, 'family', 0], [self::VIP, 'vip', 8500000]]],
                ['Umrah Business Class', 'umrah-business-class', 'Paket dengan maskapai business class dan layanan airport fast track.', 79800000, [[self::VIP, 'vip', 0], ['First Class Support', 'first', 15000000]]],
            ],
            'layanan-perlengkapan-umrah' => [
                ['Pengurusan Paspor & Dokumen', 'pengurusan-paspor-dokumen', 'Bantuan paspor, legalisir, dan kelengkapan dokumen keberangkatan.', 850000, [[self::BASIC, 'basic', 0], [self::REGULAR, 'regular', 350000]]],
                ['Handling Vaksin Meningitis', 'handling-vaksin-meningitis', 'Layanan booking, pendampingan, dan reminder vaksin meningitis.', 650000, [[self::BASIC, 'basic', 0], [self::REGULAR, 'regular', 250000]]],
                ['Perlengkapan Umrah Lengkap', 'perlengkapan-umrah-lengkap', 'Koper, tas paspor, kain ihram/mukena, buku doa, dan identitas jamaah.', 1250000, [[self::BASIC, 'basic', 0], [self::FULL, 'full', 550000]]],
                ['Manasik Private Keluarga', 'manasik-private-keluarga', 'Program manasik tatap muka khusus keluarga atau grup kecil.', 2500000, [['1 Sesi', 'sesi1', 0], ['2 Sesi + Simulasi', 'sesi2', 1200000]]],
                ['Badal Umrah & Ziarah Tambahan', 'badal-umrah-ziarah', 'Layanan koordinasi badal umrah dan ziarah tambahan sesuai kebutuhan jamaah.', 1750000, [[self::REGULAR, 'regular', 0], [self::VIP, 'vip', 950000]]],
            ],
        ]);
    }
}
