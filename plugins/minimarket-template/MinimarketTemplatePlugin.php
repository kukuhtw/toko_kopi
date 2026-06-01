<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class MinimarketTemplatePlugin implements PluginInterface
{
    private const PCS1    = '1 pcs';
    private const PACK6   = 'Pack 6 pcs';
    private const PACK12  = 'Pack 12 pcs';
    private const KG1     = '1 kg';
    private const KG5     = '5 kg';
    private const KG25    = '25 kg';
    private const GR75    = '75 gr';
    private const GR100   = '100 gr';
    private const GR200   = '200 gr';
    private const GR250   = '250 gr';
    private const GR400   = '400 gr';
    private const GR500   = '500 gr';
    private const GR800   = '800 gr';
    private const ML200   = '200 ml';
    private const ML250   = '250 ml';
    private const ML500   = '500 ml';
    private const ML1L    = '1 liter';
    private const BOT1L   = 'Botol 1 liter';
    private const BOT2L   = 'Botol 2 liter';
    private const GLON5L  = 'Galon 5 liter';
    private const BAG1    = '1 bungkus';
    private const BAG3    = 'Bundle 3 bungkus';
    private const KAN330  = '330 ml kaleng';
    private const BOX10S  = 'Box 10 sachet';

    public function getName(): string { return 'Minimarket Template'; }
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
                'url'   => '/dashboard/super/minimarket-template.php',
                'icon'  => '🏪',
                'label' => 'Minimarket Template',
            ];
        }

        return $items;
    }

    public static function getCategories(): array
    {
        return [
            ['Beras & Sembako',        'beras-sembako',        'Beras, gula, minyak, dan kebutuhan pokok.',         1],
            ['Mie & Pasta',            'mie-pasta',            'Mie instan, pasta, dan bihun.',                     2],
            ['Minuman Kemasan',        'minuman-kemasan',      'Minuman botol, kaleng, dan kotak.',                 3],
            ['Snack & Cemilan',        'snack-cemilan',        'Keripik, biskuit, permen, dan camilan.',            4],
            ['Produk Susu & Olahan',   'susu-olahan',          'Susu, keju, yogurt, dan produk olahan susu.',       5],
            ['Kopi, Teh & Minuman Serbuk', 'kopi-teh-serbuk', 'Kopi sachet, teh celup, minuman serbuk.',           6],
            ['Sabun & Deterjen',       'sabun-deterjen',       'Sabun mandi, cuci piring, deterjen pakaian.',       7],
            ['Perawatan Tubuh',        'perawatan-tubuh',      'Shampo, sabun, deodoran, dan skincare dasar.',      8],
            ['Frozen & Chilled Food',  'frozen-chilled',       'Nugget, sosis, bakso, dan produk beku.',            9],
            ['Bumbu & Rempah',         'bumbu-rempah',         'Kecap, saus, bumbu masak, dan rempah.',            10],
            ['Roti & Bakery',          'roti-bakery',          'Roti tawar, roti manis, dan kue kemasan.',         11],
            ['Kebutuhan Rumah Tangga', 'rumah-tangga',         'Tisu, kantong plastik, baterai, dan perlengkapan.', 12],
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
            return ['success' => false, 'message' => 'Seeding minimarket gagal: ' . $e->getMessage()];
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $total = array_sum(array_map('count', self::getMenuItems()));
        return ['success' => true, 'message' => "{$total} produk minimarket berhasil di-seed."];
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
        return array_merge(
            self::itemsBerasMiMinuman(),
            self::itemsSnackSusuKopi(),
            self::itemsSabunFrozenBumbu(),
            self::itemsRotiRumahTangga()
        );
    }

    private static function itemsBerasMiMinuman(): array
    {
        return self::buildItems([
            'beras-sembako' => [
                ['Beras Pulen Premium 5kg', 'beras-pulen-5kg', 'Beras pulen kualitas premium, nasi pulen dan wangi.', 68000, [[self::KG5, 'kg5', 0], [self::KG25, 'kg25', 270000]]],
                ['Beras Ramos 5kg', 'beras-ramos-5kg', 'Beras pera cocok untuk nasi goreng dan nasi uduk.', 62000, [[self::KG5, 'kg5', 0], [self::KG25, 'kg25', 250000]]],
                ['Gula Pasir Putih', 'gula-pasir-putih', 'Gula pasir putih kemasan higienis untuk masakan dan minuman.', 14500, [[self::KG1, 'kg1', 0], ['2 kg', 'kg2', 12000]]],
                ['Gula Merah Jawa', 'gula-merah-jawa', 'Gula merah asli Jawa untuk masakan tradisional dan minuman.', 18000, [[self::GR500, 'gr500', 0], [self::KG1, 'kg1', 32000]]],
                ['Minyak Goreng Kemasan', 'minyak-goreng-kemasan', 'Minyak goreng sawit jernih untuk memasak sehari-hari.', 17000, [[self::ML1L, 'liter1', 0], ['2 liter', 'liter2', 29000]]],
                ['Tepung Terigu Serbaguna', 'tepung-terigu', 'Tepung terigu protein sedang untuk kue dan masakan.', 12000, [[self::GR500, 'gr500', 0], [self::KG1, 'kg1', 20000]]],
                ['Garam Beryodium', 'garam-beryodium', 'Garam dapur beryodium untuk masakan dan pengawetan.', 3500, [[self::GR250, 'gr250', 0], [self::GR500, 'gr500', 4500]]],
                ['Telur Ayam Negeri', 'telur-ayam-negeri', 'Telur ayam segar berkualitas untuk masakan sehari-hari.', 28000, [['1 kg (±8 butir)', 'kg1', 0], ['2 kg (±16 butir)', 'kg2', 50000]]],
                ['Tepung Beras Rose Brand', 'tepung-beras', 'Tepung beras halus untuk kue dan masakan tradisional.', 9000, [[self::GR500, 'gr500', 0], [self::KG1, 'kg1', 16000]]],
                ['Susu Kental Manis Kaleng', 'susu-kental-manis', 'Susu kental manis untuk minuman, kue, dan roti bakar.', 11000, [['385 gr', 'kaleng', 0], ['2 kaleng', 'kaleng2', 19500]]],
            ],
            'mie-pasta' => [
                ['Indomie Goreng', 'indomie-goreng', 'Mi goreng instan paling populer dengan bumbu khas.', 3500, [[self::BAG1, 'bungkus', 0], [self::BAG3, 'bundle3', 8500]]],
                ['Indomie Kuah Ayam Bawang', 'indomie-ayam-bawang', 'Mi kuah instan rasa ayam bawang gurih dan lezat.', 3500, [[self::BAG1, 'bungkus', 0], [self::BAG3, 'bundle3', 8500]]],
                ['Mie Sedaap Goreng', 'mie-sedaap-goreng', 'Mi goreng dengan cita rasa gurih dan tekstur kenyal.', 3500, [[self::BAG1, 'bungkus', 0], [self::BAG3, 'bundle3', 8500]]],
                ['Mie Sedaap Soto', 'mie-sedaap-soto', 'Mi kuah rasa soto yang segar dan gurih.', 3500, [[self::BAG1, 'bungkus', 0], [self::BAG3, 'bundle3', 8500]]],
                ['Spageti Impor 500gr', 'spageti-impor', 'Spageti impor dari gandum durum, cocok untuk pasta.', 22000, [[self::GR500, 'gr500', 0], [self::KG1, 'kg1', 38000]]],
                ['Bihun Beras', 'bihun-beras', 'Bihun beras halus untuk sup, tumis, dan gorengan.', 8000, [[self::GR200, 'gr200', 0], [self::GR500, 'gr500', 16000]]],
                ['Sohun Kacang Hijau', 'sohun-kacang-hijau', 'Sohun transparan dari kacang hijau untuk sop dan masakan.', 7500, [[self::GR200, 'gr200', 0], [self::GR500, 'gr500', 15000]]],
                ['Mi Telur Kuning', 'mi-telur', 'Mi telur kering untuk capcay, mie ayam, dan tumisan.', 10000, [[self::GR200, 'gr200', 0], [self::GR500, 'gr500', 20000]]],
                ['Macaroni Elbow', 'macaroni-elbow', 'Macaroni bentuk elbow untuk sup dan mac and cheese.', 14000, [[self::GR500, 'gr500', 0], [self::KG1, 'kg1', 25000]]],
                ['Lasagna Sheet', 'lasagna-sheet', 'Lembaran pasta kering untuk lasagna panggang.', 25000, [[self::GR500, 'gr500', 0], [self::KG1, 'kg1', 45000]]],
            ],
            'minuman-kemasan' => [
                ['Aqua Air Mineral', 'aqua-600ml', 'Air mineral murni dari pegunungan, higienis dan segar.', 4000, [['600 ml', 'botol600', 0], [self::BOT1L, 'botol1l', 4000], [self::GLON5L, 'galon5l', 20000]]],
                ['Teh Botol Sosro', 'teh-botol-sosro', 'Teh manis kemasan botol kaca khas Indonesia.', 7000, [[self::BOT1L, 'botol1l', 0], ['Karton 24 botol', 'karton', 150000]]],
                ['Pocari Sweat', 'pocari-sweat', 'Minuman isotonik pengganti ion tubuh saat beraktivitas.', 8000, [['330 ml', 'kaleng330', 0], ['500 ml', 'botol500', 4000]]],
                ['Coca-Cola', 'coca-cola', 'Minuman soda paling populer di dunia.', 7000, [[self::KAN330, 'kaleng', 0], [self::BOT1L, 'botol1l', 6000]]],
                ['Sprite', 'sprite', 'Minuman soda rasa lemon-lime yang segar.', 7000, [[self::KAN330, 'kaleng', 0], [self::BOT1L, 'botol1l', 6000]]],
                ['Fanta Strawberry', 'fanta-strawberry', 'Minuman soda rasa stroberi berwarna merah cerah.', 7000, [[self::KAN330, 'kaleng', 0], [self::BOT1L, 'botol1l', 6000]]],
                ['Ultra Milk Full Cream', 'ultra-milk-fullcream', 'Susu UHT full cream rasa original bergizi tinggi.', 5500, [[self::ML200, 'ml200', 0], [self::ML1L, 'liter1', 18000]]],
                ['Jus ABC Rasa Jeruk', 'jus-abc-jeruk', 'Minuman jus rasa jeruk segar dalam kemasan kotak.', 5000, [[self::ML200, 'ml200', 0], [self::ML1L, 'liter1', 18000]]],
                ['Good Day Kopi Susu', 'good-day-kopisusu', 'Minuman kopi susu RTD dengan cita rasa creamy.', 6500, [['220 ml', 'ml220', 0], [self::PACK6, 'pack6', 32000]]],
                ['Floridina Rasa Jeruk', 'floridina-jeruk', 'Minuman ringan rasa jeruk manis dalam botol plastik.', 6000, [[self::BOT1L, 'botol1l', 0], [self::BOT2L, 'botol2l', 9000]]],
            ],
        ]);
    }

    private static function itemsSnackSusuKopi(): array
    {
        return self::buildItems([
            'snack-cemilan' => [
                ['Chitato Rasa Sapi Panggang', 'chitato-sapi', 'Keripik kentang rasa sapi panggang crispy dan gurih.', 12000, [[self::GR200, 'gr200', 0], ['68 gr (besar)', 'gr68', -3000]]],
                ['Lays Rasa Original', 'lays-original', 'Keripik kentang tipis dan renyah rasa original.', 11000, [[self::GR200, 'gr200', 0], ['68 gr (besar)', 'gr68', -3000]]],
                ['Oreo Biskuit Coklat', 'oreo-coklat', 'Biskuit sandwich dengan krim coklat di tengah.', 8500, [['119.6 gr', 'gr120', 0], ['432 gr', 'gr432', 27000]]],
                ['Roma Kelapa', 'roma-kelapa', 'Biskuit kelapa renyah dan manis favorit keluarga.', 6000, [['150 gr', 'gr150', 0], ['420 gr', 'gr420', 14000]]],
                ['Tango Wafer Coklat', 'tango-coklat', 'Wafer berlapis coklat dengan tekstur renyah.', 5000, [['130 gr', 'gr130', 0], ['390 gr', 'gr390', 12000]]],
                ['Nabati Richeese', 'nabati-richeese', 'Wafer keju yang renyah dan gurih.', 2000, [['28 gr', 'gr28', 0], [self::PACK12, 'pack12', 18000]]],
                ['Permen Kopiko Coffee Candy', 'permen-kopiko', 'Permen rasa kopi pekat untuk pecinta kopi.', 4500, [['120 gr', 'gr120', 0], ['800 gr (toples)', 'toples', 28000]]],
                ['Chiki Balls Keju', 'chiki-balls-keju', 'Snack bola jagung rasa keju yang renyah.', 3000, [['50 gr', 'gr50', 0], [self::PACK6, 'pack6', 15000]]],
                ['Malkist Crackers', 'malkist-crackers', 'Biskuit crackers gurih cocok untuk cemilan kapan saja.', 7500, [[self::GR100, 'gr100', 0], ['300 gr', 'gr300', 19000]]],
                ['Superstar Popcorn Caramel', 'popcorn-caramel', 'Popcorn manis rasa karamel dalam kemasan praktis.', 8000, [['65 gr', 'gr65', 0], [self::GR200, 'gr200', 18000]]],
            ],
            'susu-olahan' => [
                ['Indomilk Susu Full Cream', 'indomilk-fullcream', 'Susu UHT full cream dalam kemasan karton siap minum.', 5000, [[self::ML200, 'ml200', 0], [self::ML1L, 'liter1', 17000]]],
                ['Frisian Flag Susu Cair', 'frisian-flag-cair', 'Susu segar UHT berkualitas tinggi dari Frisian Flag.', 6000, [[self::ML200, 'ml200', 0], [self::ML1L, 'liter1', 20000]]],
                ['Yomost Yogurt Strawberry', 'yomost-strawberry', 'Minuman yogurt rasa stroberi segar dan probiotik.', 6000, [['150 ml', 'ml150', 0], [self::PACK6, 'pack6', 30000]]],
                ['Milkuat Susu Anak Coklat', 'milkuat-coklat', 'Susu anak rasa coklat bergizi tinggi untuk pertumbuhan.', 3500, [['150 ml', 'ml150', 0], [self::PACK6, 'pack6', 18000]]],
                ['Keju Kraft Singles', 'keju-kraft-singles', 'Keju slice individual untuk sandwich dan burger.', 18000, [['10 slice', 'slice10', 0], ['20 slice', 'slice20', 30000]]],
                ['Keju Prochiz Gold', 'keju-prochiz', 'Keju blok lokal berkualitas untuk masakan dan olesan.', 20000, [['170 gr', 'gr170', 0], [self::GR500, 'gr500', 52000]]],
                ['Dancow Full Cream', 'dancow-fullcream', 'Susu bubuk full cream bergizi untuk keluarga.', 15000, [[self::GR200, 'gr200', 0], [self::GR800, 'gr800', 52000]]],
                ['Nestle Milo Powder', 'milo-powder', 'Susu bubuk coklat berenergi untuk anak dan remaja.', 18000, [[self::GR200, 'gr200', 0], ['1 kg', 'kg1', 75000]]],
                ['Cimory Yogurt Greek', 'cimory-greek', 'Yogurt Greek kental tinggi protein rendah lemak.', 22000, [['180 gr', 'gr180', 0], [self::GR500, 'gr500', 52000]]],
                ['Elle & Vire Butter', 'elle-vire-butter', 'Mentega impor berkualitas untuk memanggang dan memasak.', 25000, [[self::GR100, 'gr100', 0], [self::GR500, 'gr500', 95000]]],
            ],
            'kopi-teh-serbuk' => [
                ['Kapal Api Kopi Hitam', 'kapal-api-hitam', 'Kopi hitam tubruk klasik Indonesia tanpa gula.', 2500, [['26 gr sachet', 'sachet', 0], ['165 gr', 'gr165', 13000]]],
                ['Nescafe Classic', 'nescafe-classic', 'Kopi instan murni tanpa campuran, aroma kuat.', 3000, [['2 gr sachet', 'sachet', 0], [self::GR100, 'gr100', 38000]]],
                ['Indocafe Coffeemix', 'indocafe-coffeemix', 'Kopi susu 3in1 rasa lembut dan creamy.', 2500, [['20 gr sachet', 'sachet', 0], [self::BOX10S, 'box10', 22000]]],
                ['Good Day Cappuccino', 'good-day-cappuccino', 'Minuman cappuccino instan dengan busa creamy.', 2500, [['25 gr sachet', 'sachet', 0], [self::BOX10S, 'box10', 22000]]],
                ['Teh Celup Sariwangi', 'sariwangi-celup', 'Teh hitam celup pilihan untuk teh hangat sehari-hari.', 7000, [['Box 25 celup', 'box25', 0], ['Box 100 celup', 'box100', 23000]]],
                ['Teh Sosro Kotak', 'teh-sosro-kotak', 'Teh siap saji dalam kemasan kotak praktis.', 5000, [[self::ML200, 'ml200', 0], [self::PACK6, 'pack6', 25000]]],
                ['Milo 3in1 Aktif', 'milo-3in1', 'Minuman coklat malt bergizi dan berenergi.', 3000, [['27 gr sachet', 'sachet', 0], [self::BOX10S, 'box10', 27000]]],
                ['Energen Coklat', 'energen-coklat', 'Sereal susu coklat bergizi untuk sarapan praktis.', 4000, [['30 gr sachet', 'sachet', 0], [self::BOX10S, 'box10', 35000]]],
                ['Ovomaltine Powder', 'ovomaltine', 'Minuman serbuk coklat malt dengan nutrisi lengkap.', 18000, [[self::GR200, 'gr200', 0], [self::GR500, 'gr500', 42000]]],
                ['Jahe Instan Nutrisari', 'jahe-instan', 'Minuman jahe instan hangat untuk tubuh dan imunitas.', 3500, [['15 gr sachet', 'sachet', 0], [self::BOX10S, 'box10', 30000]]],
            ],
        ]);
    }

    private static function itemsSabunFrozenBumbu(): array
    {
        return self::buildItems([
            'sabun-deterjen' => [
                ['Rinso Anti Noda', 'rinso-anti-noda', 'Deterjen bubuk penghilang noda membandel.', 18000, [[self::GR500, 'gr500', 0], ['1.8 kg', 'kg18', 48000]]],
                ['Soklin Liquid', 'soklin-liquid', 'Deterjen cair lembut untuk pakaian warna dan putih.', 15000, [[self::ML500, 'ml500', 0], ['1.8 liter', 'liter18', 42000]]],
                ['Attack Plus Softener', 'attack-softener', 'Deterjen dengan pelembut pakaian dalam satu kemasan.', 18000, [[self::GR500, 'gr500', 0], ['1.8 kg', 'kg18', 48000]]],
                ['Sunlight Cuci Piring', 'sunlight-jeruk', 'Sabun cuci piring rasa jeruk ampuh dan irit busa.', 8000, [[self::ML250, 'ml250', 0], [self::ML500, 'ml500', 14000]]],
                ['Wings Biru Deterjen', 'wings-biru', 'Deterjen ekonomis untuk pakaian sehari-hari.', 5000, [[self::GR400, 'gr400', 0], ['900 gr', 'gr900', 10000]]],
                ['Sabun Mandi Lifebuoy', 'lifebuoy-batang', 'Sabun mandi antibakteri perlindungan penuh.', 5000, [[self::GR75, 'gr75', 0], [self::PACK6, 'pack6', 25000]]],
                ['Dettol Antiseptik Cair', 'dettol-antiseptik', 'Cairan antiseptik untuk kebersihan tubuh dan permukaan.', 18000, [[self::ML250, 'ml250', 0], [self::ML500, 'ml500', 32000]]],
                ['Wipol Karbol Wangi', 'wipol-karbol', 'Cairan pembersih lantai aroma bunga menyegarkan.', 12000, [[self::ML500, 'ml500', 0], [self::BOT1L, 'liter1', 18000]]],
                ['Softener Molto Pink', 'molto-pink', 'Pelembut dan pewangi pakaian aroma bunga.', 15000, [[self::ML500, 'ml500', 0], [self::ML1L, 'liter1', 25000]]],
                ['Pembersih WC Domestos', 'domestos-wc', 'Cairan pembersih toilet desinfektan ampuh.', 12000, [[self::ML500, 'ml500', 0], [self::ML1L, 'liter1', 20000]]],
            ],
            'perawatan-tubuh' => [
                ['Shampo Pantene Smooth', 'pantene-smooth', 'Shampo pelembut rambut untuk rambut halus berkilau.', 18000, [[self::ML250, 'ml250', 0], [self::ML500, 'ml500', 30000]]],
                ['Shampo Head & Shoulders', 'head-shoulders', 'Shampo anti ketombe efektif untuk rambut sehat.', 18000, [[self::ML250, 'ml250', 0], [self::ML500, 'ml500', 30000]]],
                ['Dove Body Wash', 'dove-body-wash', 'Sabun mandi cair Dove lembut untuk kulit sensitif.', 20000, [[self::ML250, 'ml250', 0], [self::ML500, 'ml500', 35000]]],
                ['Vaseline Lotion Healthy White', 'vaseline-lotion', 'Lotion pelembap kulit tubuh untuk kulit cerah dan lembut.', 18000, [[self::ML250, 'ml250', 0], [self::ML500, 'ml500', 30000]]],
                ['Citra Lasting White Lotion', 'citra-lotion', 'Lotion pemutih kulit dengan ekstrak bengkoang dan VitC.', 16000, [[self::ML250, 'ml250', 0], [self::ML500, 'ml500', 26000]]],
                ['Deodoran Rexona Men', 'rexona-men', 'Deodoran roll-on pria anti keringat 48 jam.', 18000, [[self::PCS1, 'pcs', 0], [self::PACK6, 'pack6', 88000]]],
                ['Pasta Gigi Pepsodent', 'pepsodent-pasta', 'Pasta gigi fluoride perlindungan gigi dan gusi.', 12000, [[self::GR75, 'gr75', 0], ['190 gr', 'gr190', 25000]]],
                ['Sikat Gigi Formula', 'sikat-gigi-formula', 'Sikat gigi bulu lembut untuk perlindungan optimal.', 8000, [[self::PCS1, 'pcs', 0], [self::PACK6, 'pack6', 38000]]],
                ['Minyak Rambut Gatsby', 'gatsby-hair', 'Minyak rambut untuk rambut rapi dan berkilau.', 15000, [[self::GR75, 'gr75', 0], ['175 gr', 'gr175', 28000]]],
                ['Sunsilk Kondisioner', 'sunsilk-kondisioner', 'Kondisioner rambut untuk rambut lembut dan mudah diatur.', 18000, [[self::ML250, 'ml250', 0], [self::ML500, 'ml500', 30000]]],
            ],
            'frozen-chilled' => [
                ['Fiesta Nugget Ayam', 'fiesta-nugget', 'Nugget ayam beku siap goreng, renyah dan gurih.', 28000, [[self::GR500, 'gr500', 0], ['1 kg', 'kg1', 50000]]],
                ['So Good Sosis Ayam', 'so-good-sosis', 'Sosis ayam lezat untuk sarapan dan bekal anak.', 22000, [['500 gr (±10 pcs)', 'gr500', 0], ['1 kg (±20 pcs)', 'kg1', 38000]]],
                ['Bakso Ikan Surimi', 'bakso-ikan', 'Bakso ikan kenyal untuk sup, mie, dan tumisan.', 18000, [[self::GR250, 'gr250', 0], [self::GR500, 'gr500', 30000]]],
                ['Kornet Sapi Pronas', 'kornet-sapi', 'Kornet sapi olahan siap makan untuk sarapan cepat.', 12000, [[self::GR200, 'gr200', 0], [self::GR400, 'gr400', 20000]]],
                ['Chicken Katsu Fiesta', 'chicken-katsu', 'Ayam katsu beku dilapisi tepung roti, crispy enak.', 32000, [[self::GR400, 'gr400', 0], [self::KG1, 'kg1', 65000]]],
                ['Udang Kupas Beku', 'udang-kupas-beku', 'Udang segar dikupas dan dibekukan, siap masak.', 45000, [[self::GR250, 'gr250', 0], [self::GR500, 'gr500', 80000]]],
                ['Daging Giling Sapi Beku', 'daging-giling-beku', 'Daging sapi giling beku untuk bakso, burger, dan saus.', 55000, [[self::GR250, 'gr250', 0], [self::GR500, 'gr500', 95000]]],
                ['Es Krim Walls Cornetto', 'walls-cornetto', 'Es krim cone dengan coklat dan kacang khas Cornetto.', 9000, [[self::PCS1, 'pcs', 0], [self::PACK6, 'pack6', 48000]]],
                ['Aice Mochi Ice Cream', 'aice-mochi', 'Es krim mochi isi aneka rasa dalam satu pack.', 10000, [['10 pcs', 'pcs10', 0], ['20 pcs', 'pcs20', 18000]]],
                ['Frozen Dimsum Siomay', 'frozen-siomay', 'Siomay beku isi udang dan daging siap kukus/goreng.', 25000, [[self::GR250, 'gr250', 0], [self::GR500, 'gr500', 45000]]],
            ],
            'bumbu-rempah' => [
                ['Kecap Manis Bango', 'kecap-bango', 'Kecap manis premium dari kedelai hitam pilihan.', 12000, [[self::ML250, 'ml250', 0], [self::ML500, 'ml500', 20000]]],
                ['Kecap Asin ABC', 'kecap-asin-abc', 'Kecap asin untuk memasak, cocolan, dan sushi.', 8000, [[self::ML250, 'ml250', 0], [self::ML500, 'ml500', 14000]]],
                ['Saus Tomat ABC', 'saus-tomat-abc', 'Saus tomat manis asam untuk makanan dan camilan.', 9000, [['330 gr', 'gr330', 0], [self::GR800, 'gr800', 19000]]],
                ['Saus Sambal ABC Ekstra Pedas', 'saus-sambal-abc', 'Saus sambal ekstra pedas untuk pencinta cabai.', 9000, [['330 gr', 'gr330', 0], [self::GR800, 'gr800', 19000]]],
                ['Royco Kaldu Ayam', 'royco-ayam', 'Penyedap rasa kaldu ayam untuk masakan lebih gurih.', 3000, [['7 gr sachet', 'sachet', 0], ['95 gr', 'gr95', 12000]]],
                ['Bumbu Nasi Goreng Indofood', 'bumbu-nasgor', 'Bumbu nasi goreng instan siap pakai aroma autentik.', 3500, [['20 gr sachet', 'sachet', 0], ['Box 5 sachet', 'box5', 15000]]],
                ['Santan Kara Kelapa', 'santan-kara', 'Santan kelapa instan dalam kemasan untuk masakan.', 4500, [['65 ml', 'ml65', 0], [self::ML200, 'ml200', 10000]]],
                ['Merica Bubuk Lada Hitam', 'merica-bubuk', 'Merica hitam bubuk untuk bumbu masak dan taburan.', 8000, [['30 gr', 'gr30', 0], [self::GR100, 'gr100', 20000]]],
                ['Cuka Makan Dixi', 'cuka-makan', 'Cuka makan untuk acar, tumisan, dan masakan asam.', 6000, [[self::ML250, 'ml250', 0], [self::ML500, 'ml500', 10000]]],
                ['Bumbu Rendang Instan', 'bumbu-rendang', 'Bumbu rendang siap pakai untuk hasil masakan sempurna.', 12000, [['100 gr (2 porsi)', 'gr100', 0], ['250 gr (5 porsi)', 'gr250', 25000]]],
            ],
        ]);
    }

    private static function itemsRotiRumahTangga(): array
    {
        return self::buildItems([
            'roti-bakery' => [
                ['Roti Tawar Sari Roti', 'roti-tawar-sariroti', 'Roti tawar lembut dan fresh untuk sarapan dan sandwich.', 16000, [['1 bungkus (14 lembar)', 'bungkus', 0], ['Family Size (20 lembar)', 'family', 8000]]],
                ['Roti Gandum Whole Wheat', 'roti-gandum', 'Roti gandum utuh tinggi serat untuk pilihan sehat.', 22000, [['1 bungkus', 'bungkus', 0], ['2 bungkus', 'bundle2', 38000]]],
                ['Croissant Butter', 'croissant-butter', 'Croissant renyah berlapis butter asli dari oven.', 8000, [[self::PCS1, 'pcs', 0], [self::PACK6, 'pack6', 42000]]],
                ['Roti Coklat Aoka', 'roti-coklat-aoka', 'Roti manis isi coklat lembut kemasan menarik.', 3500, [[self::PCS1, 'pcs', 0], [self::PACK6, 'pack6', 18000]]],
                ['Bread Talk Cheese Roll', 'breadtalk-cheese', 'Roti gulung isi keju lumer favorit semua kalangan.', 12000, [[self::PCS1, 'pcs', 0], ['3 pcs bundle', 'bundle3', 32000]]],
                ['Biskuit Marie Regal', 'marie-regal', 'Biskuit marie klasik renyah untuk cemilan dan bahan kue.', 9000, [[self::GR250, 'gr250', 0], [self::GR500, 'gr500', 16000]]],
                ['Roti Sobek Kasur Keju', 'roti-sobek-keju', 'Roti sobek lembut dengan isian keju yang melimpah.', 18000, [['1 loyang', 'loyang', 0], ['2 loyang', 'bundle2', 32000]]],
                ['Donat Glaze Original', 'donat-glaze', 'Donat empuk dengan lapisan glaze manis berwarna-warni.', 6000, [[self::PCS1, 'pcs', 0], ['6 pcs box', 'box6', 32000]]],
                ['Bakpao Isi Kacang Hijau', 'bakpao-kacang', 'Bakpao kukus lembut isi kacang hijau manis.', 5000, [[self::PCS1, 'pcs', 0], ['6 pcs bundle', 'bundle6', 25000]]],
                ['Roti Gambang Jahe', 'roti-gambang', 'Roti tradisional dengan rasa jahe dan wijen khas.', 7000, [['2 pcs', 'pcs2', 0], ['6 pcs bundle', 'bundle6', 35000]]],
            ],
            'rumah-tangga' => [
                ['Tisu Paseo Multifold', 'tisu-paseo', 'Tisu wajah lembut untuk kebersihan sehari-hari.', 12000, [['130 lembar', 'pcs130', 0], ['3 box bundle', 'bundle3', 30000]]],
                ['Tisu Toilet Paseo', 'tisu-toilet-paseo', 'Tisu toilet lembut dan kuat, mudah larut dalam air.', 18000, [['4 roll', 'roll4', 0], ['12 roll', 'roll12', 45000]]],
                ['Kantong Plastik Kresek', 'kantong-kresek', 'Kantong plastik kresek serbaguna ukuran sedang.', 8000, [['100 lembar', 'pcs100', 0], ['500 lembar', 'pcs500', 30000]]],
                ['Baterai ABC AA', 'baterai-abc-aa', 'Baterai alkaline AA tahan lama untuk berbagai perangkat.', 12000, [['2 pcs', 'pcs2', 0], ['4 pcs', 'pcs4', 20000]]],
                ['Korek Api Gas Tokai', 'korek-gas-tokai', 'Korek api gas isi ulang berkualitas tahan lama.', 8000, [[self::PCS1, 'pcs', 0], [self::PACK6, 'pack6', 40000]]],
                ['Kantong Sampah Hitam', 'kantong-sampah', 'Kantong sampah plastik tebal ukuran besar.', 15000, [['25 pcs', 'pcs25', 0], ['50 pcs', 'pcs50', 25000]]],
                ['Aluminium Foil Dapur', 'aluminium-foil', 'Aluminium foil dapur untuk memanggang dan menyimpan.', 18000, [['30 meter', 'm30', 0], ['60 meter', 'm60', 30000]]],
                ['Lilin Lebah Parafin', 'lilin-parafin', 'Lilin darurat berkualitas untuk penerangan dan dekorasi.', 7000, [['6 batang', 'pcs6', 0], ['12 batang', 'pcs12', 12000]]],
                ['Spons Cuci Piring', 'spons-cuci', 'Spons scrubber dua sisi untuk mencuci piring efektif.', 5000, [[self::PCS1, 'pcs', 0], [self::PACK6, 'pack6', 25000]]],
                ['Lap Pel Microfiber', 'lap-pel', 'Kain pel microfiber menyerap air maksimal tanpa bekas.', 25000, [[self::PCS1, 'pcs', 0], ['3 pcs bundle', 'bundle3', 60000]]],
            ],
        ]);
    }
}
