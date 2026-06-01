<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class HpAccessoriesTemplatePlugin implements PluginInterface
{
    // Variant label constants
    private const SAM   = 'Samsung Series';
    private const IPH   = 'iPhone Series';
    private const AND   = 'OPPO/Vivo/Xiaomi';
    private const UNI   = 'Universal';
    private const SM    = 'Small (5-6 inch)';
    private const LG    = 'Large (6.5-7 inch)';
    private const REG   = 'Regular';
    private const PRO   = 'Pro / Premium';
    private const M1    = '1 meter';
    private const M2    = '2 meter';
    private const PCS1  = '1 pcs';
    private const PACK2 = 'Pack 2 pcs';

    public function getName(): string { return 'Toko Aksesori & Casing HP Template'; }
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
                'url'   => '/dashboard/super/hp-accessories-template.php',
                'icon'  => '📱',
                'label' => 'Aksesori HP Template',
            ];
        }
        return $items;
    }

    public static function getCategories(): array
    {
        return [
            ['Casing & Cover HP',        'casing-cover',    'Soft case, hard case, flip cover, dan casing premium.',   1],
            ['Pelindung Layar',           'pelindung-layar', 'Tempered glass, hydrogel film, dan privacy screen.',      2],
            ['Kabel & Charger',           'kabel-charger',   'Kabel data, charger fast charging, dan wireless charger.', 3],
            ['Earphone & Headset',        'earphone-headset','TWS, earphone in-ear, headset, dan neckband bluetooth.',  4],
            ['Power Bank',               'power-bank',      'Power bank slim, fast charge, wireless, dan solar.',       5],
            ['Holder & Mount',            'holder-mount',    'Holder mobil, tripod, selfie stick, dan ring stand.',     6],
            ['Aksesori HP Lainnya',       'aksesori-lainnya','Lanyard, pop socket, garskin, lens clip, dan lainnya.',  7],
            ['Aksesori Gaming HP',        'aksesori-gaming', 'Trigger, gamepad, cooling fan, dan aksesori gaming mobile.', 8],
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
                    foreach ($item['variants'] as $vSort => $variant) {
                        if (!is_array($variant) || count($variant) < 3) {
                            continue;
                        }
                        [$label, $vSlug, $delta] = $variant;
                        if ($label === null || $vSlug === null) {
                            continue;
                        }
                        $stmtVariant->execute([
                            ':item_id' => $itemId,
                            ':label'   => (string) $label,
                            ':slug'    => $itemId . '-' . (string) $vSlug,
                            ':delta'   => (float) $delta,
                            ':sort'    => $vSort + 1,
                        ]);
                    }
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            return ['success' => false, 'message' => 'Seeding aksesori HP gagal: ' . $e->getMessage()];
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $total = array_sum(array_map('count', self::getMenuItems()));
        return ['success' => true, 'message' => "{$total} produk aksesori HP berhasil di-seed."];
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
            self::itemsCasingPelindungKabelEarphone(),
            self::itemsPowerBankHolderAksesoriGaming()
        );
    }

    private static function itemsCasingPelindungKabelEarphone(): array
    {
        return self::buildItems([
            'casing-cover' => [
                ['Soft Case Transparan', 'soft-case-transparan', 'Case TPU bening tipis anti-kuning, pelindung ringan namun kuat.', 15000, [[self::SAM, 'sam', 0], [self::IPH, 'iph', 5000], [self::AND, 'and', 0]]],
                ['Hard Case Matte Premium', 'hard-case-matte', 'Case PC matte anti sidik jari, tampilan elegan dan slim.', 25000, [[self::SAM, 'sam', 0], [self::IPH, 'iph', 5000], [self::AND, 'and', 0]]],
                ['Flip Cover Leather', 'flip-cover-leather', 'Flip case kulit sintetis dengan slot kartu dan cermin kecil.', 45000, [[self::SAM, 'sam', 0], [self::IPH, 'iph', 10000], [self::AND, 'and', 0]]],
                ['Armor Case Shockproof', 'armor-case-shockproof', 'Case hybrid dual-layer tahan banting, pelindung sudut dan layar.', 65000, [[self::SAM, 'sam', 0], [self::IPH, 'iph', 10000], [self::AND, 'and', 0]]],
                ['Silicone Case Warna', 'silicone-case-warna', 'Case silikon lembut warna-warni dengan tekstur nyaman di tangan.', 18000, [[self::SAM, 'sam', 0], [self::IPH, 'iph', 5000], [self::AND, 'and', 0]]],
                ['Ring Case Stand Holder', 'ring-case-stand', 'Case dengan ring metal 360° untuk pegangan dan stand.', 22000, [[self::SAM, 'sam', 0], [self::IPH, 'iph', 5000], [self::AND, 'and', 0]]],
                ['Casing Motif Aesthetic', 'casing-motif-aesthetic', 'Case motif bunga dan alam aesthetic, cocok untuk konten kreator.', 20000, [[self::SAM, 'sam', 0], [self::IPH, 'iph', 5000], [self::AND, 'and', 0]]],
                ['Glitter Sparkling Case', 'glitter-sparkling', 'Case glitter liquid dengan efek berkilau, cantik dan unik.', 22000, [[self::SM, 'small', 0], [self::LG, 'large', 3000]]],
                ['Wallet Case Dompet', 'wallet-case', 'Case dompet dengan slot kartu dan uang, praktis all-in-one.', 55000, [[self::SAM, 'sam', 0], [self::IPH, 'iph', 10000], [self::AND, 'and', 0]]],
                ['MagSafe Clear Case', 'magsafe-clear', 'Case transparan kompatibel MagSafe untuk pengisian wireless magnetik.', 85000, [['iPhone 13', 'iph13', 0], ['iPhone 14', 'iph14', 0], ['iPhone 15', 'iph15', 5000]]],
            ],
            'pelindung-layar' => [
                ['Tempered Glass Clear 9H', 'tg-clear-9h', 'Kaca tempered 9H bening jernih anti gores untuk perlindungan layar.', 15000, [[self::SM, 'small', 0], [self::LG, 'large', 3000]]],
                ['Tempered Glass Anti Glare', 'tg-anti-glare', 'Kaca tempered matte anti silau untuk penggunaan di luar ruangan.', 18000, [[self::SM, 'small', 0], [self::LG, 'large', 3000]]],
                ['Tempered Glass Full Cover', 'tg-full-cover', 'Kaca tempered full lem menutupi seluruh permukaan layar.', 22000, [[self::SAM, 'sam', 0], [self::IPH, 'iph', 5000], [self::AND, 'and', 0]]],
                ['Hydrogel Film Anti Scratch', 'hydrogel-film', 'Film hydrogel fleksibel anti gores, self-healing dari goresan halus.', 20000, [[self::SM, 'small', 0], [self::LG, 'large', 3000]]],
                ['Privacy Screen Protector', 'privacy-screen', 'Anti-spy screen protector, layar hanya terlihat dari depan.', 25000, [[self::SAM, 'sam', 0], [self::IPH, 'iph', 5000], [self::AND, 'and', 0]]],
                ['Tempered Glass Kamera', 'tg-kamera', 'Pelindung lensa kamera dari goresan dan benturan.', 12000, [[self::PCS1, 'pcs', 0], [self::PACK2, 'pack2', 10000]]],
                ['Screen Protector Matte', 'sp-matte', 'Pelindung layar matte anti fingerprint, bebas sidik jari.', 18000, [[self::SM, 'small', 0], [self::LG, 'large', 3000]]],
                ['Full Glue Tempered Glass', 'tg-full-glue', 'Tempered glass full glue tanpa bagian putih di sisi layar.', 25000, [[self::SAM, 'sam', 0], [self::IPH, 'iph', 5000], [self::AND, 'and', 0]]],
                ['Anti Shock Hydrogel', 'hydrogel-antishock', 'Hydrogel tahan benturan, melindungi layar dari jatuh ringan.', 22000, [[self::SM, 'small', 0], [self::LG, 'large', 3000]]],
                ['Nano Liquid Screen Protector', 'nano-liquid', 'Pelindung layar nano liquid tidak tampak, perlindungan kimia.', 30000, [[self::UNI, 'uni', 0], ['Botol isi 2', 'botol2', 25000]]],
            ],
            'kabel-charger' => [
                ['Kabel USB-C Data & Charging', 'kabel-usbc', 'Kabel USB-C untuk pengisian dan transfer data cepat.', 25000, [[self::M1, 'm1', 0], [self::M2, 'm2', 10000]]],
                ['Kabel Lightning iPhone', 'kabel-lightning', 'Kabel Lightning original kompatibel untuk iPhone dan iPad.', 35000, [[self::M1, 'm1', 0], [self::M2, 'm2', 12000]]],
                ['Kabel Braided Nylon USB-C', 'kabel-braided', 'Kabel USB-C nylon anyam tahan lama, anti-kusut.', 45000, [[self::M1, 'm1', 0], [self::M2, 'm2', 10000]]],
                ['Charger Fast Charging 33W', 'charger-33w', 'Adaptor charger cepat 33W kompatibel dengan berbagai merek HP.', 85000, [[self::REG, 'reguler', 0], ['Dual Port', 'dual', 25000]]],
                ['Charger GaN USB-C 65W', 'charger-gan-65w', 'Charger GaN 65W ultra-kompak untuk laptop dan HP sekaligus.', 125000, [[self::REG, 'reguler', 0], ['Dual Port PD', 'dual', 45000]]],
                ['Wireless Charger Qi 15W', 'wireless-charger-15w', 'Wireless charger Qi 15W pad pengisian tanpa kabel.', 95000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 35000]]],
                ['Charger Mobil Dual USB', 'charger-mobil', 'Adaptor charger mobil dual USB 36W untuk perjalanan.', 45000, [[self::REG, 'reguler', 0], ['Type-C + USB', 'combo', 15000]]],
                ['USB Hub 4-in-1', 'usb-hub-4in1', 'USB hub 4 port untuk laptop, mendukung USB 3.0 dan Type-C.', 65000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 35000]]],
                ['Magnetic Cable 3-in-1', 'magnetic-cable-3in1', 'Kabel magnetik 3-in-1 (USB-C, Lightning, Micro) mudah lepas pasang.', 35000, [[self::M1, 'm1', 0], [self::M2, 'm2', 10000]]],
                ['Kabel Micro USB', 'kabel-microusb', 'Kabel Micro USB untuk HP Android lama, kokoh dan irit.', 15000, [[self::M1, 'm1', 0], [self::M2, 'm2', 8000]]],
            ],
            'earphone-headset' => [
                ['TWS Bluetooth Earbuds', 'tws-bluetooth', 'True wireless stereo earbuds dengan case pengisi daya, bass kuat.', 150000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 100000]]],
                ['Earphone In-Ear Wired', 'earphone-inear-wired', 'Earphone kabel in-ear dengan bass boost dan mikrofon.', 45000, [['3.5mm Jack', 'jack', 0], ['USB-C', 'usbc', 5000]]],
                ['Earphone Type-C', 'earphone-typec', 'Earphone kabel USB-C untuk HP tanpa jack 3.5mm.', 55000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 30000]]],
                ['Headset Gaming RGB', 'headset-gaming-rgb', 'Headset over-ear dengan LED RGB dan mikrofon surround untuk gaming.', 185000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 65000]]],
                ['Neckband Bluetooth', 'neckband-bluetooth', 'Headset neckband bluetooth dengan baterai tahan lama.', 125000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 50000]]],
                ['Wired Headset Stereo', 'wired-headset-stereo', 'Headset kabel dengan speaker besar untuk kualitas audio terbaik.', 75000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 50000]]],
                ['Bone Conduction Headphone', 'bone-conduction', 'Headphone konduksi tulang untuk olahraga, aman dan tidak menutup telinga.', 350000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 150000]]],
                ['Earphone Sport Waterproof', 'earphone-sport', 'Earphone tahan air IPX5 untuk olahraga lari dan gym.', 85000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 65000]]],
                ['Mini Clip-On Earbuds', 'mini-clip-on', 'Earbuds mini clip-on ultra-kecil, cocok untuk konten kreator.', 95000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 55000]]],
                ['ANC Wireless Earbuds', 'anc-earbuds', 'Earbuds wireless dengan Active Noise Cancellation untuk fokus.', 285000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 115000]]],
            ],
        ]);
    }

    private static function itemsPowerBankHolderAksesoriGaming(): array
    {
        return self::buildItems([
            'power-bank' => [
                ['Power Bank 10.000mAh Slim', 'pb-10000-slim', 'Power bank ultra-slim 10.000mAh, ringan dan mudah dibawa.', 120000, [[self::REG, 'reguler', 0], ['Fast Charge', 'fc', 30000]]],
                ['Power Bank 20.000mAh', 'pb-20000', 'Power bank kapasitas besar 20.000mAh fast charge dual output.', 185000, [[self::REG, 'reguler', 0], ['Fast Charge 65W', 'fc65', 65000]]],
                ['Power Bank Mini 5000mAh', 'pb-5000-mini', 'Power bank mini 5000mAh saku, ringan hanya 100 gram.', 75000, [[self::REG, 'reguler', 0], ['With Cable', 'cable', 15000]]],
                ['Power Bank Solar 10.000mAh', 'pb-solar', 'Power bank panel surya, pengisian darurat tanpa listrik.', 145000, [[self::REG, 'reguler', 0], ['20.000mAh', 'large', 65000]]],
                ['Power Bank Wireless 10.000mAh', 'pb-wireless', 'Power bank dengan wireless charging pad terintegrasi.', 165000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 65000]]],
                ['Power Bank 30.000mAh', 'pb-30000', 'Power bank kapasitas jumbo untuk perjalanan panjang.', 245000, [[self::REG, 'reguler', 0], ['Fast Charge', 'fc', 55000]]],
                ['Power Bank Built-in Cable', 'pb-built-cable', 'Power bank dengan kabel built-in 3-in-1, tanpa kabel terpisah.', 135000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 45000]]],
                ['Power Bank MagSafe 5000mAh', 'pb-magsafe', 'Power bank magnetic MagSafe untuk iPhone, snap-on langsung.', 195000, [['iPhone 13', 'iph13', 0], ['iPhone 14/15', 'iph1415', 25000]]],
                ['Power Bank LED Display', 'pb-led-display', 'Power bank dengan layar LED menampilkan persentase baterai.', 145000, [['10.000mAh', '10k', 0], ['20.000mAh', '20k', 55000]]],
                ['Power Bank Outdoor Rugged', 'pb-outdoor-rugged', 'Power bank tahan air dan benturan untuk aktivitas outdoor.', 175000, [['10.000mAh', '10k', 0], ['20.000mAh', '20k', 75000]]],
            ],
            'holder-mount' => [
                ['Holder HP Dashboard Mobil', 'holder-dashboard', 'Holder HP untuk dashboard mobil, rotasi 360° dan grip kuat.', 35000, [[self::REG, 'reguler', 0], ['Magnetic', 'mag', 20000]]],
                ['Ring Stand Holder Jari', 'ring-stand-jari', 'Ring holder untuk jari dengan stand 360° dan magnet untuk car mount.', 15000, [[self::REG, 'reguler', 0], ['Metal Premium', 'metal', 10000]]],
                ['Tripod Mini Flexible', 'tripod-mini-flex', 'Tripod mini dengan kaki fleksibel, bisa diikat di mana saja.', 45000, [[self::REG, 'reguler', 0], ['+ Remote Shutter', 'remote', 15000]]],
                ['Selfie Stick Bluetooth', 'selfie-stick-bt', 'Selfie stick bluetooth telescopic dengan tripod built-in.', 65000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 35000]]],
                ['Holder Sepeda & Motor', 'holder-sepeda-motor', 'Holder HP untuk setang sepeda dan motor, tahan getaran dan air.', 45000, [[self::REG, 'reguler', 0], ['With Raincover', 'rain', 15000]]],
                ['Magnetic Car Mount', 'magnetic-car-mount', 'Car mount magnetik untuk ventilasi AC, 360° rotasi.', 55000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 25000]]],
                ['Desktop Stand Adjustable', 'desktop-stand', 'Stand meja HP/tablet adjustable tinggi untuk kebutuhan WFH.', 55000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 35000]]],
                ['Gorilla Pod Flexible', 'gorilla-pod', 'Tripod fleksibel dapat ditekuk mengikuti permukaan apapun.', 85000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 65000]]],
                ['Fidget Ring Stand 360°', 'fidget-ring-360', 'Ring stand fidget dengan grip anti-licin dan rotasi 360°.', 20000, [[self::PCS1, 'pcs', 0], [self::PACK2, 'pack2', 15000]]],
                ['Wall Mount Holder', 'wall-mount-holder', 'Holder tempel dinding tanpa paku untuk dapur dan kamar.', 35000, [[self::REG, 'reguler', 0], [self::PACK2, 'pack2', 45000]]],
            ],
            'aksesori-lainnya' => [
                ['Tali Lanyard HP Lucu', 'lanyard-hp-lucu', 'Tali HP lucu warna-warni bisa dikalungkan, anti jatuh.', 15000, [[self::REG, 'reguler', 0], [self::PACK2, 'pack2', 22000]]],
                ['Pop Socket Custom', 'pop-socket', 'Pop socket lipat untuk pegangan HP dan stand sederhana.', 20000, [[self::REG, 'reguler', 0], [self::PACK2, 'pack2', 30000]]],
                ['Garskin Stiker HP', 'garskin-stiker', 'Stiker garskin motif aesthetic untuk body HP, anti gores.', 25000, [[self::UNI, 'uni', 0], ['Custom Print', 'custom', 15000]]],
                ['Dust Plug USB-C', 'dust-plug-usbc', 'Penutup port USB-C dari debu dan air, satu set 5 buah.', 8000, [['USB-C (5 pcs)', 'usbc', 0], ['Lightning (5 pcs)', 'lightning', 2000]]],
                ['Cleaning Kit Layar HP', 'cleaning-kit', 'Kit pembersih layar HP: kain microfiber + cairan pembersih.', 18000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 12000]]],
                ['Card Holder Stick-On', 'card-holder-stickon', 'Tempat kartu tempel belakang HP, muat 3 kartu.', 15000, [[self::REG, 'reguler', 0], [self::PACK2, 'pack2', 22000]]],
                ['Wide Angle Lens Clip', 'lens-wide-angle', 'Lensa wide angle 0.45x clip-on untuk foto landscape lebih lebar.', 35000, [[self::REG, 'reguler', 0], ['Set 3 Lens', 'set3', 35000]]],
                ['Macro Lens Clip 25x', 'lens-macro-25x', 'Lensa macro 25x untuk foto detail serangga dan produk.', 30000, [[self::REG, 'reguler', 0], ['Set 3 Lens', 'set3', 35000]]],
                ['Fisheye Lens Clip', 'lens-fisheye', 'Lensa fisheye 180° untuk efek foto unik dan kreatif.', 30000, [[self::REG, 'reguler', 0], ['Set 3 Lens', 'set3', 35000]]],
                ['Remote Shutter Bluetooth', 'remote-shutter-bt', 'Remote shutter bluetooth untuk foto selfie tanpa sentuh HP.', 25000, [[self::REG, 'reguler', 0], ['+ Tripod Mini', 'tripod', 30000]]],
            ],
            'aksesori-gaming' => [
                ['Trigger Gamepad PUBG/ML', 'trigger-gamepad', 'Trigger L1R1 untuk game mobile FPS dan battle royale.', 35000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 15000]]],
                ['Controller Gamepad Bluetooth', 'controller-bt', 'Gamepad bluetooth clip-on kompatibel dengan hampir semua HP.', 185000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 85000]]],
                ['Cooling Fan HP Gaming', 'cooling-fan-hp', 'Kipas pendingin HP untuk mencegah throttling saat gaming.', 55000, [[self::REG, 'reguler', 0], ['RGB Version', 'rgb', 25000]]],
                ['Trigger L1R1 Joystick', 'trigger-l1r1', 'Set trigger + mini joystick untuk kontrol game FPS lebih presisi.', 25000, [[self::REG, 'reguler', 0], [self::PACK2, 'pack2', 40000]]],
                ['Mini Joystick HP', 'mini-joystick-hp', 'Joystick mini tempel layar untuk game mobile.', 18000, [[self::PCS1, 'pcs', 0], [self::PACK2, 'pack2', 28000]]],
                ['Gaming Headset Mobile', 'gaming-headset-mobile', 'Headset gaming 3.5mm/USB-C untuk suara game lebih imersif.', 125000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 75000]]],
                ['Gaming Keyboard Mini BT', 'gaming-keyboard-mini', 'Keyboard mini bluetooth dengan backlit RGB untuk HP & tablet.', 250000, [[self::REG, 'reguler', 0], [self::PRO, 'pro', 125000]]],
                ['USB OTG Adapter', 'usb-otg', 'Adapter OTG untuk menghubungkan perangkat USB ke HP Android.', 15000, [['USB-C OTG', 'usbc', 0], ['Micro USB OTG', 'micro', 0]]],
                ['Stand HP Gaming Desktop', 'stand-hp-gaming', 'Stand HP desktop untuk gaming landscape, cooling ventilasi.', 65000, [[self::REG, 'reguler', 0], ['RGB Version', 'rgb', 35000]]],
                ['LED Case Gaming RGB', 'led-case-rgb', 'Case HP dengan LED RGB di belakang yang menyala saat ada notifikasi.', 75000, [[self::SAM, 'sam', 0], [self::IPH, 'iph', 10000], [self::AND, 'and', 0]]],
            ],
        ]);
    }
}
