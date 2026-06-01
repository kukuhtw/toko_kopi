<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class FashionWanitaTemplatePlugin implements PluginInterface
{
    // Size variant constants
    private const S    = 'S';
    private const M    = 'M';
    private const L    = 'L';
    private const XL   = 'XL';
    private const XXL  = 'XXL';
    private const OSML = 'S-M (Allsize)';
    private const OLLX = 'L-XL (Allsize)';
    private const FREE = 'Free Size';

    public function getName(): string { return 'Toko Baju Busana Wanita Template'; }
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
                'url'   => '/dashboard/super/fashion-wanita-template.php',
                'icon'  => '👗',
                'label' => 'Fashion Wanita Template',
            ];
        }
        return $items;
    }

    public static function getCategories(): array
    {
        return [
            ['Atasan Wanita',        'atasan-wanita',     'Blouse, kemeja, kaos, crop top, dan rajut wanita.',          1],
            ['Bawahan Wanita',       'bawahan-wanita',    'Celana, rok, legging, palazzo, dan jogger wanita.',           2],
            ['Dress & Tunik',        'dress-tunik',       'Midi dress, maxi dress, bodycon, wrap dress, dan tunik.',     3],
            ['Outer & Jaket',        'outer-jaket',       'Cardigan, blazer, jaket denim, bomber, dan trench coat.',     4],
            ['Baju Muslim & Gamis',  'baju-muslim-gamis', 'Gamis syari, abaya, kaftan, tunik muslim, dan setelan.',      5],
            ['Pakaian Casual',       'pakaian-casual',    'Piyama, loungewear, oversize tee, set olahraga, dan daster.', 6],
            ['Pakaian Formal & Kerja', 'pakaian-formal',  'Kemeja formal, blazer set, dress kerja, dan jumpsuit.',       7],
            ['Aksesori Fashion',     'aksesori-fashion',  'Tas, dompet, topi, kacamata, syal, perhiasan, dan headband.', 8],
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
            return ['success' => false, 'message' => 'Seeding fashion wanita gagal: ' . $e->getMessage()];
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $total = array_sum(array_map('count', self::getMenuItems()));
        return ['success' => true, 'message' => "{$total} produk fashion wanita berhasil di-seed."];
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
            self::itemsAtasanBawahanDressOuter(),
            self::itemsMuslimCasualFormalAksesori()
        );
    }

    private static function itemsAtasanBawahanDressOuter(): array
    {
        return self::buildItems([
            'atasan-wanita' => [
                ['Blouse Rayon Motif Floral', 'blouse-rayon-floral', 'Blouse rayon bermotif bunga floral, ringan dan adem untuk aktivitas sehari-hari.', 85000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Crop Top Cotton Basic', 'crop-top-cotton', 'Crop top katun polos basic warna solid, cocok dipadukan rok atau celana tinggi.', 55000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Kemeja Casual Wanita', 'kemeja-casual-wanita', 'Kemeja casual polos atau motif kotak-kotak untuk tampilan chic sehari-hari.', 95000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Kaos Polos Premium Wanita', 'kaos-polos-premium', 'Kaos polos cotton combed 30s premium lembut dan tidak mudah luntur.', 65000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Blouse Sifon Tali Bahu', 'blouse-sifon-tali', 'Blouse sifon ringan dengan tali bahu, feminin dan elegan untuk acara kasual.', 75000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Tank Top Wanita Casual', 'tank-top-casual', 'Tank top basic warna solid, cocok untuk inner atau dipakai sendiri.', 45000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Atasan Rajut Knit', 'atasan-rajut-knit', 'Atasan rajut knit stylish, hangat dan cocok untuk musim hujan atau AC dingin.', 120000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Polo Shirt Wanita', 'polo-shirt-wanita', 'Polo shirt wanita kerah rapi, cocok untuk kasual formal dan smart casual.', 85000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Blouse Batik Modern', 'blouse-batik-modern', 'Blouse batik cap motif kontemporer, memadukan tradisi dan gaya modern.', 110000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Turtleneck Sweater Wanita', 'turtleneck-sweater', 'Sweater turtleneck hangat berbahan soft knit, cocok untuk cuaca dingin.', 145000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
            ],
            'bawahan-wanita' => [
                ['Celana Kulot Wanita', 'celana-kulot', 'Celana kulot longgar berbahan crepe, nyaman dan stylish untuk kerja dan santai.', 95000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Rok Midi Flared', 'rok-midi-flared', 'Rok midi A-line flared berbahan sifon, mengembang cantik saat berputar.', 85000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Legging Sport Premium', 'legging-sport', 'Legging sport high-waist berbahan spandex, nyaman untuk gym dan yoga.', 65000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Celana Jeans Skinny Wanita', 'jeans-skinny-wanita', 'Jeans skinny stretch wanita, pas di badan dengan 4-way stretch.', 145000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Rok Mini A-Line', 'rok-mini-aline', 'Rok mini A-line berbahan tweed atau denim, tampilan edgy dan fashionable.', 75000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Wide Leg Pants', 'wide-leg-pants', 'Celana wide leg berbahan linen, tampilan chic dan modern dengan potongan longgar.', 125000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Rok Plisket Panjang', 'rok-plisket-panjang', 'Rok plisket panjang midi berbahan sifon, elegan untuk pesta dan formal.', 95000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Celana Pendek Denim', 'celana-pendek-denim', 'Celana pendek denim high-waist, cocok untuk casual outing dan pantai.', 85000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Palazzo Pants Wanita', 'palazzo-pants', 'Celana palazzo flowy lebar berbahan rayon, nyaman seharian dipakai.', 115000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Celana Jogger Wanita', 'celana-jogger-wanita', 'Celana jogger wanita berbahan fleece, santai tapi tetap stylish.', 95000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
            ],
            'dress-tunik' => [
                ['Midi Dress Floral', 'midi-dress-floral', 'Midi dress bermotif bunga floral berbahan rayon, feminine dan segar.', 135000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Maxi Dress Rayon', 'maxi-dress-rayon', 'Maxi dress panjang berbahan rayon lembut, cocok untuk pantai dan jalan-jalan.', 155000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Mini Dress Casual', 'mini-dress-casual', 'Mini dress casual berbahan cotton atau jersey, nyaman untuk hangout.', 95000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Wrap Dress Elegant', 'wrap-dress-elegant', 'Wrap dress dengan tali ikat pinggang, bentuk tubuh indah dan elegan.', 165000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Tunik Batik Wanita', 'tunik-batik', 'Tunik panjang bermotif batik cap, memadukan unsur tradisi dan kekinian.', 115000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Tunik Linen Casual', 'tunik-linen-casual', 'Tunik berbahan linen breathable, cocok untuk iklim tropis.', 95000, [[self::OSML, 'sm', 0], [self::OLLX, 'lxl', 10000]]],
                ['Bodycon Dress', 'bodycon-dress', 'Dress ketat bodycon berbahan spandex, menonjolkan lekuk tubuh.', 125000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Shirt Dress Button-Down', 'shirt-dress-button', 'Dress model kemeja dengan kancing depan, versatile untuk casual dan semi-formal.', 145000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Sundress Pantai', 'sundress-pantai', 'Sundress ringan bermotif tropis, sempurna untuk liburan pantai.', 115000, [[self::OSML, 'sm', 0], [self::OLLX, 'lxl', 10000]]],
                ['Dress Brokat Mini', 'dress-brokat-mini', 'Dress brokat mini untuk pesta dan acara formal, mewah dan berkelas.', 185000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 15000]]],
            ],
            'outer-jaket' => [
                ['Cardigan Rajut Oversized', 'cardigan-rajut-oversized', 'Cardigan rajut oversized longgar, cocok untuk layering dan tampilan santai.', 145000, [[self::OSML, 'sm', 0], [self::OLLX, 'lxl', 10000]]],
                ['Blazer Formal Wanita', 'blazer-formal-wanita', 'Blazer formal berbahan wool blend, rapi untuk meeting dan presentasi.', 195000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 15000]]],
                ['Jaket Denim Wanita', 'jaket-denim-wanita', 'Jaket denim klasik wanita, timeless dan cocok dipadukan apa saja.', 185000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Bomber Jacket Wanita', 'bomber-jacket-wanita', 'Bomber jacket wanita berbahan satin atau nylon, sporty dan fashionable.', 165000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Trench Coat Wanita', 'trench-coat-wanita', 'Trench coat panjang berbahan gabardine, elegan dan tahan angin.', 285000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 20000]]],
                ['Hoodie Wanita Fleece', 'hoodie-wanita-fleece', 'Hoodie berbahan fleece tebal dengan kantong depan, nyaman dan hangat.', 145000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Windbreaker Jacket', 'windbreaker-jacket', 'Jaket windbreaker ringan tahan angin, cocok untuk outdoor dan olahraga.', 175000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Kimono Outer Motif', 'kimono-outer-motif', 'Kimono outer bermotif bunga atau batik, cocok untuk layering dan ke pantai.', 95000, [[self::OSML, 'sm', 0], [self::OLLX, 'lxl', 10000]]],
                ['Vest Rajut Wanita', 'vest-rajut-wanita', 'Vest rajut sleeveless untuk layering di atas kemeja atau blouse.', 115000, [[self::OSML, 'sm', 0], [self::OLLX, 'lxl', 10000]]],
                ['Jaket Kulit Sintetis', 'jaket-kulit-sintetis', 'Jaket kulit sintetis faux leather, edgy dan chic untuk tampilan bold.', 245000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 20000]]],
            ],
        ]);
    }

    private static function itemsMuslimCasualFormalAksesori(): array
    {
        return self::buildItems([
            'baju-muslim-gamis' => [
                ['Gamis Syari Polos', 'gamis-syari-polos', 'Gamis syari berbahan ceruti atau wolfis polos, menutup aurat dengan sempurna.', 185000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000], [self::XXL, 'xxl', 20000]]],
                ['Gamis Motif Batik', 'gamis-motif-batik', 'Gamis bermotif batik kontemporer, anggun dan cocok untuk acara resmi.', 215000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000], [self::XXL, 'xxl', 20000]]],
                ['Abaya Dubai Polos', 'abaya-dubai', 'Abaya berbahan crepe premium ala Dubai, elegan dan mewah.', 225000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 15000], [self::XXL, 'xxl', 25000]]],
                ['Kaftan Wanita Rayon', 'kaftan-rayon', 'Kaftan rayon motif etnik, cocok untuk acara santai dan liburan.', 145000, [[self::OSML, 'sm', 0], [self::OLLX, 'lxl', 15000]]],
                ['Setelan Wanita Muslim', 'setelan-muslim', 'Setelan atasan dan celana/rok berbahan linen atau crepe, matching dan rapi.', 195000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000], [self::XXL, 'xxl', 20000]]],
                ['Tunik Muslim Casual', 'tunik-muslim-casual', 'Tunik muslim berbahan viscose, nyaman untuk aktivitas sehari-hari.', 115000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000], [self::XXL, 'xxl', 10000]]],
                ['Gamis Brokat Premium', 'gamis-brokat-premium', 'Gamis brokat mewah dengan detail bordir, untuk pesta dan lebaran.', 285000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 20000], [self::XXL, 'xxl', 35000]]],
                ['Dress Muslim Modern', 'dress-muslim-modern', 'Dress muslim modern cutting A-line berbahan jersy, stylish dan syari.', 165000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000], [self::XXL, 'xxl', 20000]]],
                ['Atasan Muslim Casual', 'atasan-muslim-casual', 'Atasan muslim berbahan katun atau rayon, casual untuk aktivitas harian.', 95000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000], [self::XXL, 'xxl', 10000]]],
                ['Rok Panjang Syari', 'rok-panjang-syari', 'Rok panjang berbahan ceruti atau katun, simpel dan bisa dipadukan banyak atasan.', 95000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000], [self::XXL, 'xxl', 10000]]],
            ],
            'pakaian-casual' => [
                ['Set Piyama Wanita', 'set-piyama-wanita', 'Set piyama katun lengan pendek dan celana panjang, nyaman untuk tidur.', 115000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Loungewear Set', 'loungewear-set', 'Set loungewear crop top dan celana panjang, stylish untuk di rumah.', 145000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Oversize T-Shirt Wanita', 'oversize-tshirt-wanita', 'Kaos oversize wanita berbahan cotton tebal, trendy dan nyaman.', 75000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Celana Training Wanita', 'celana-training-wanita', 'Celana training berbahan fleece atau dry-fit untuk olahraga dan santai.', 85000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Baju Tidur Satin', 'baju-tidur-satin', 'Baju tidur berbahan satin halus, mewah dan nyaman di kulit.', 95000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Set Olahraga Wanita', 'set-olahraga-wanita', 'Set sport bra dan legging high-waist untuk gym, yoga, atau lari.', 155000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Kaos Sablon Wanita', 'kaos-sablon-wanita', 'Kaos sablon wanita berbahan cotton combed, desain aesthetic dan trendy.', 65000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Daster Wanita Motif', 'daster-motif', 'Daster wanita bermotif batik atau bunga, nyaman untuk di rumah.', 75000, [[self::OSML, 'sm', 0], [self::OLLX, 'lxl', 10000]]],
                ['Set Homewear Santai', 'set-homewear-santai', 'Set homewear atasan dan celana matching berbahan jersey lembut.', 95000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
                ['Bralette Non-Kawat', 'bralette-non-kawat', 'Bralette nyaman tanpa kawat berbahan cotton atau spandex breathable.', 65000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 5000]]],
            ],
            'pakaian-formal' => [
                ['Kemeja Formal Wanita', 'kemeja-formal-wanita', 'Kemeja berbahan poplin atau sifon untuk tampilan profesional di kantor.', 145000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Blazer + Rok Set Formal', 'blazer-rok-set', 'Setelan blazer dan rok pensil matching, sempurna untuk interview dan meeting.', 285000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 20000]]],
                ['Celana Bahan Formal', 'celana-bahan-formal', 'Celana bahan berbahan polyester atau wool blend, rapi untuk kerja.', 165000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Dress Formal Kerja', 'dress-formal-kerja', 'Dress formal knee-length berbahan structured untuk lingkungan kerja profesional.', 245000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 20000]]],
                ['Blouse Sifon Formal', 'blouse-sifon-formal', 'Blouse sifon formal dengan lengan panjang, elegan dan profesional.', 125000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
                ['Setelan Rok Pensil', 'setelan-rok-pensil', 'Setelan atasan dan rok pensil formal, tampilan power dressing wanita karir.', 225000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 15000]]],
                ['Blazer Tweed Premium', 'blazer-tweed-premium', 'Blazer tweed berkelas berbahan campuran wool, cocok untuk acara resmi.', 255000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 20000]]],
                ['Jumpsuit Formal', 'jumpsuit-formal', 'Jumpsuit celana berbahan crepe atau satin, tampilan unik namun tetap formal.', 195000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 15000]]],
                ['Dress Kerja A-Line', 'dress-kerja-aline', 'Dress A-line knee-length untuk kerja, cutting yang mempercantik siluet tubuh.', 215000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 15000]]],
                ['Kemeja Batik Formal', 'kemeja-batik-formal', 'Kemeja batik formal wanita untuk acara resmi dan hari batik nasional.', 165000, [[self::S, 's', 0], [self::M, 'm', 0], [self::L, 'l', 0], [self::XL, 'xl', 10000]]],
            ],
            'aksesori-fashion' => [
                ['Tas Tote Bag Canvas', 'tas-tote-canvas', 'Tote bag kanvas polos atau bermotif, praktis untuk belanja dan kerja.', 75000, [[self::FREE, 'free', 0], ['Premium Tebal', 'premium', 30000]]],
                ['Dompet Wanita Panjang', 'dompet-wanita-panjang', 'Dompet panjang wanita kulit sintetis dengan banyak slot kartu dan uang.', 95000, [[self::FREE, 'free', 0], ['Premium Kulit', 'premium', 55000]]],
                ['Ikat Pinggang Wanita', 'ikat-pinggang-wanita', 'Belt wanita untuk mempercantik siluet pada dress, tunik, atau blazer.', 45000, [[self::FREE, 'free', 0], ['Kulit Premium', 'premium', 35000]]],
                ['Topi Bucket Hat', 'topi-bucket-hat', 'Topi bucket berbahan canvas atau denim, trendy untuk outing dan pantai.', 55000, [[self::FREE, 'free', 0], ['Premium Wool', 'premium', 35000]]],
                ['Kacamata Hitam Fashion', 'kacamata-hitam-fashion', 'Sunglasses wanita UV400 berbagai model: cat eye, oval, square.', 65000, [[self::FREE, 'free', 0], ['Polarized', 'polarized', 45000]]],
                ['Syal Scarf Motif', 'syal-scarf-motif', 'Syal scarf berbahan sifon atau pashmina, bisa sebagai hijab, belt, atau aksesori.', 45000, [[self::FREE, 'free', 0], ['Premium Pashmina', 'premium', 30000]]],
                ['Kalung Charm Wanita', 'kalung-charm', 'Kalung charm wanita dengan liontin lucu, gold atau silver plated.', 55000, [[self::FREE, 'free', 0], ['Set + Anting', 'set', 35000]]],
                ['Gelang Wanita Set', 'gelang-wanita-set', 'Set gelang wanita 3-5 pcs stacking bracelet, bohemian atau minimalis.', 45000, [[self::FREE, 'free', 0], ['Gold Plated', 'gold', 25000]]],
                ['Headband Rambut', 'headband-rambut', 'Headband wanita berbagai model: knotted, padded, scrunchie, dan pearl.', 25000, [[self::FREE, 'free', 0], ['Set 3 pcs', 'set3', 45000]]],
                ['Sabuk Kulit Wanita', 'sabuk-kulit-wanita', 'Belt kulit sintetis wanita untuk celana atau dress, buckle gold atau silver.', 55000, [[self::FREE, 'free', 0], ['Kulit Asli', 'genuine', 75000]]],
            ],
        ]);
    }
}
