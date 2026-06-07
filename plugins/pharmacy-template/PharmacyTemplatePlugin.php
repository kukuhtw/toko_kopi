<?php

declare(strict_types=1);

use KopiBot\Contracts\PluginInterface;
use KopiBot\Core\DatabaseConnection;
use KopiBot\Core\HookManager;

class PharmacyTemplatePlugin implements PluginInterface
{
    private const S10TAB    = 'Strip 10 tab';
    private const B100TAB   = 'Box 100 tab';
    private const B50TAB    = 'Box 50 tab';
    private const B30TAB    = 'Box 30 tab';
    private const S10KAP    = 'Strip 10 kap';
    private const B100KAP   = 'Box 100 kap';
    private const B60KAP    = 'Box 60 kap';
    private const BOT60ML   = 'Botol 60 ml';
    private const BOT120ML  = 'Botol 120 ml';
    private const BOT100ML  = 'Botol 100 ml';
    private const BOT200ML  = 'Botol 200 ml';
    private const B10SACHET = 'Box 10 sachet';
    private const B100PCS   = 'Box 100 pcs';
    private const T5GR      = 'Tube 5 gr';
    private const T10GR     = 'Tube 10 gr';
    private const T15GR     = 'Tube 15 gr';
    private const T30GR     = 'Tube 30 gr';

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
        $pdo = DatabaseConnection::getInstance();
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
            self::itemsObatAlergiAntibiotik(),
            self::itemsLambungBatukVitamin(),
            self::itemsAntiseptikKulitKronis(),
            self::itemsAlatIbuKebersihan()
        );
    }

    private static function itemsObatAlergiAntibiotik(): array
    {
        return self::buildItems([
            'obat-demam-nyeri' => [
                ['Paracetamol 500mg', 'paracetamol-500mg', 'Pereda demam dan nyeri ringan hingga sedang.', 8000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 55000]]],
                ['Ibuprofen 400mg', 'ibuprofen-400mg', 'Antiinflamasi nonsteroid, pereda nyeri dan demam.', 12000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 85000]]],
                ['Aspirin 80mg', 'aspirin-80mg', 'Antiplatelet dosis rendah untuk pengencer darah.', 15000, [[self::S10TAB, 'strip', 0], [self::B30TAB, 'box', 35000]]],
                ['Mefenamic Acid 500mg', 'mefenamic-acid-500mg', 'Pereda nyeri haid, sakit gigi, dan nyeri pasca operasi.', 18000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 130000]]],
                ['Naproxen 500mg', 'naproxen-500mg', 'Antiinflamasi untuk nyeri otot dan sendi.', 20000, [[self::S10TAB, 'strip', 0], [self::B50TAB, 'box', 75000]]],
                ['Ketorolac 10mg', 'ketorolac-10mg', 'Pereda nyeri kuat non-opioid pasca operasi.', 25000, [[self::S10TAB, 'strip', 0], [self::B30TAB, 'box', 60000]]],
                ['Diclofenac 25mg', 'diclofenac-25mg', 'Antiinflamasi untuk nyeri sendi dan rematik.', 14000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 100000]]],
                ['Paracetamol Syrup', 'paracetamol-syrup', 'Pereda demam dalam bentuk sirup, cocok untuk anak dan dewasa.', 22000, [[self::BOT60ML, 'botol-60', 0], [self::BOT120ML, 'botol-120', 15000]]],
                ['Tramadol HCl 50mg', 'tramadol-50mg', 'Pereda nyeri sedang-berat, penggunaan dengan resep dokter.', 30000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 220000]]],
                ['Metamizole 500mg', 'metamizole-500mg', 'Analgesik dan antipiretik kuat untuk nyeri dan demam tinggi.', 16000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 120000]]],
            ],
            'alergi-flu' => [
                ['Cetirizine 10mg', 'cetirizine-10mg', 'Antihistamin generasi kedua untuk alergi kulit, mata, dan pilek.', 10000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 70000]]],
                ['Loratadine 10mg', 'loratadine-10mg', 'Antihistamin non-sedatif untuk rhinitis alergi dan urtikaria.', 12000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 80000]]],
                ['Chlorpheniramine 4mg', 'ctm-4mg', 'Antihistamin generasi pertama, efektif untuk alergi dan pilek.', 5000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 35000]]],
                ['Fexofenadine 120mg', 'fexofenadine-120mg', 'Antihistamin tanpa rasa kantuk untuk alergi musiman.', 20000, [[self::S10TAB, 'strip', 0], [self::B30TAB, 'box', 50000]]],
                ['Desloratadine 5mg', 'desloratadine-5mg', 'Antihistamin aktif untuk urtikaria dan alergi hidung.', 25000, [[self::S10TAB, 'strip', 0], [self::B30TAB, 'box', 60000]]],
                ['Pseudoephedrine 60mg', 'pseudoephedrine-60mg', 'Dekongestan hidung tersumbat akibat flu dan sinusitis.', 18000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 130000]]],
                ['Oxymetazoline Nasal Spray', 'oxymetazoline-spray', 'Semprot hidung untuk melegakan hidung tersumbat.', 35000, [['Botol 10 ml', 'botol-10', 0], ['Botol 15 ml', 'botol-15', 15000]]],
                ['Diphenhydramine 50mg', 'diphenhydramine-50mg', 'Antihistamin sekaligus anti mabuk perjalanan dan insomnia ringan.', 8000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 55000]]],
                ['Promethazine 25mg', 'promethazine-25mg', 'Antihistamin untuk mual, alergi berat, dan mabuk perjalanan.', 15000, [[self::S10TAB, 'strip', 0], [self::B50TAB, 'box', 60000]]],
                ['Bilastine 20mg', 'bilastine-20mg', 'Antihistamin terbaru untuk alergi kulit dan rhinitis alergi.', 30000, [[self::S10TAB, 'strip', 0], [self::B30TAB, 'box', 75000]]],
            ],
            'antibiotik' => [
                ['Amoxicillin 500mg', 'amoxicillin-500mg', 'Antibiotik spectrum luas untuk infeksi saluran napas dan kulit.', 15000, [[self::S10KAP, 'strip', 0], [self::B100KAP, 'box', 110000]]],
                ['Amoxicillin 250mg', 'amoxicillin-250mg', 'Amoxicillin dosis rendah untuk infeksi ringan dan anak-anak.', 10000, [[self::S10KAP, 'strip', 0], [self::B100KAP, 'box', 75000]]],
                ['Azithromycin 500mg', 'azithromycin-500mg', 'Antibiotik makrolida untuk infeksi saluran napas dan kulit.', 25000, [['Strip 3 tab', 'strip', 0], [self::B30TAB, 'box', 200000]]],
                ['Ciprofloxacin 500mg', 'ciprofloxacin-500mg', 'Antibiotik fluorokuinolon untuk infeksi saluran kemih dan pencernaan.', 20000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 150000]]],
                ['Erythromycin 500mg', 'erythromycin-500mg', 'Antibiotik makrolida alternatif untuk alergi penisilin.', 18000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 130000]]],
                ['Cefadroxil 500mg', 'cefadroxil-500mg', 'Sefalosporin generasi pertama untuk infeksi kulit dan tenggorokan.', 22000, [[self::S10KAP, 'strip', 0], [self::B100KAP, 'box', 170000]]],
                ['Doxycycline 100mg', 'doxycycline-100mg', 'Tetrasiklin untuk infeksi akne, malaria, dan infeksi bakteri umum.', 12000, [[self::S10KAP, 'strip', 0], [self::B100KAP, 'box', 85000]]],
                ['Cefixime 100mg', 'cefixime-100mg', 'Sefalosporin generasi ketiga untuk infeksi berat saluran kemih.', 30000, [[self::S10TAB, 'strip', 0], [self::B50TAB, 'box', 120000]]],
                ['Metronidazole 500mg', 'metronidazole-500mg', 'Antibiotik anaerob dan antiprotozoa untuk infeksi gigi dan usus.', 10000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 70000]]],
                ['Cotrimoxazole 480mg', 'cotrimoxazole-480mg', 'Kombinasi sulfamethoxazole + trimethoprim untuk infeksi saluran kemih.', 8000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 55000]]],
            ],
        ]);
    }

    private static function itemsLambungBatukVitamin(): array
    {
        return self::buildItems([
            'lambung-pencernaan' => [
                ['Omeprazole 20mg', 'omeprazole-20mg', 'Penghambat pompa proton untuk maag, GERD, dan tukak lambung.', 15000, [[self::S10KAP, 'strip', 0], [self::B100KAP, 'box', 110000]]],
                ['Antasida DOEN Tablet', 'antasida-tablet', 'Menetralisir asam lambung untuk meredakan maag dan kembung.', 5000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 35000]]],
                ['Ranitidin 150mg', 'ranitidin-150mg', 'Penghambat histamin H2 untuk maag dan refluks asam.', 8000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 55000]]],
                ['Lansoprazole 30mg', 'lansoprazole-30mg', 'PPI untuk tukak lambung dan esofagitis erosif.', 18000, [[self::S10KAP, 'strip', 0], [self::B100KAP, 'box', 130000]]],
                ['Sucralfate Suspensi', 'sucralfate-suspensi', 'Pelindung mukosa lambung untuk tukak duodenum.', 30000, [[self::BOT100ML, 'botol-100', 0], [self::BOT200ML, 'botol-200', 25000]]],
                ['Domperidone 10mg', 'domperidone-10mg', 'Antiemetik dan prokinetik untuk mual dan lambung tidak bergerak.', 12000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 85000]]],
                ['Loperamide 2mg', 'loperamide-2mg', 'Antidiare untuk mengurangi frekuensi dan volume buang air besar.', 8000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 55000]]],
                ['Oralit Sachet', 'oralit-sachet', 'Larutan rehidrasi oral untuk diare dan dehidrasi.', 3000, [['Sachet 200 ml', 'sachet', 0], [self::B10SACHET, 'box-10', 20000]]],
                ['Norit Activated Charcoal', 'norit-tablet', 'Karbon aktif untuk menyerap racun dan mengatasi diare akut.', 12000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 85000]]],
                ['Metoclopramide 10mg', 'metoclopramide-10mg', 'Prokinetik dan antiemetik untuk mual pascaoperasi dan GERD.', 10000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 70000]]],
            ],
            'batuk-tenggorokan' => [
                ['OBH Combi Syrup', 'obh-combi-syrup', 'Obat batuk kombinasi untuk batuk berdahak dan kering.', 28000, [[self::BOT60ML, 'botol-60', 0], [self::BOT100ML, 'botol-100', 18000]]],
                ['Ambroxol 30mg', 'ambroxol-30mg', 'Mukolitik untuk mengencerkan dahak pada batuk produktif.', 10000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 70000]]],
                ['Bromhexine 8mg', 'bromhexine-8mg', 'Ekspektoran untuk melonggarkan lendir di saluran napas.', 8000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 55000]]],
                ['Dextromethorphan 15mg', 'dextromethorphan-15mg', 'Antitusif untuk batuk kering non-produktif.', 8000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 55000]]],
                ['Guaifenesin 100mg', 'guaifenesin-100mg', 'Ekspektoran untuk memudahkan pengeluaran dahak.', 7000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 48000]]],
                ['Strepsils Lozenges', 'strepsils-lozenges', 'Pelega tenggorokan dengan antiseptik ringan.', 18000, [['Strip 4 pcs', 'strip', 0], ['Box 24 pcs', 'box', 85000]]],
                ['Betadine Gargle 125ml', 'betadine-gargle', 'Obat kumur antiseptik untuk infeksi tenggorokan dan mulut.', 35000, [['Botol 125 ml', 'botol', 0], ['Botol 250 ml', 'botol-250', 30000]]],
                ['Dekstrometorfan Syrup', 'dmp-syrup', 'Sirup antitusif untuk batuk kering pada anak dan dewasa.', 22000, [[self::BOT60ML, 'botol-60', 0], [self::BOT100ML, 'botol-100', 15000]]],
                ['Madu Hitam Pahit', 'madu-hitam-pahit', 'Herbal pereda batuk dan penjaga imunitas.', 45000, [['Botol 150 ml', 'botol-150', 0], ['Botol 300 ml', 'botol-300', 40000]]],
                ['Lozenges Herbal', 'lozenges-herbal', 'Pelega tenggorokan dengan bahan herbal jahe dan madu.', 12000, [['Strip 4 pcs', 'strip', 0], ['Box 24 pcs', 'box', 55000]]],
            ],
            'vitamin-suplemen' => [
                ['Vitamin C 1000mg', 'vitamin-c-1000mg', 'Antioksidan dan pendukung imunitas dosis tinggi.', 15000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 110000]]],
                ['Vitamin D3 1000 IU', 'vitamin-d3-1000iu', 'Suplemen vitamin D untuk tulang, imunitas, dan kesehatan umum.', 18000, [[self::S10KAP, 'strip', 0], [self::B60KAP, 'box', 85000]]],
                ['Vitamin B Complex', 'vitamin-b-complex', 'Kombinasi vitamin B1, B6, B12 untuk saraf dan energi.', 12000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 85000]]],
                ['Zinc 10mg', 'zinc-10mg', 'Mineral esensial untuk imunitas, penyembuhan luka, dan pertumbuhan.', 10000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 70000]]],
                ['Kalsium + Vitamin D', 'kalsium-vitamin-d', 'Suplemen tulang untuk mencegah osteoporosis.', 20000, [[self::S10TAB, 'strip', 0], ['Box 60 tab', 'box', 90000]]],
                ['Omega-3 Fish Oil 1000mg', 'omega3-fish-oil', 'Suplemen asam lemak esensial untuk jantung dan otak.', 25000, [[self::S10KAP, 'strip', 0], [self::B100KAP, 'box', 180000]]],
                ['Multivitamin Tablet', 'multivitamin', 'Multivitamin lengkap untuk kebutuhan nutrisi harian.', 18000, [[self::S10TAB, 'strip', 0], [self::B30TAB, 'box', 45000]]],
                ['Vitamin E 400 IU', 'vitamin-e-400iu', 'Antioksidan untuk kulit, rambut, dan sistem imun.', 15000, [[self::S10KAP, 'strip', 0], [self::B60KAP, 'box', 70000]]],
                ['Asam Folat 400mcg', 'asam-folat-400mcg', 'Suplemen folat untuk ibu hamil dan kesehatan sel darah merah.', 10000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 70000]]],
                ['Magnesium 250mg', 'magnesium-250mg', 'Mineral untuk relaksasi otot, tidur, dan metabolisme energi.', 20000, [[self::S10TAB, 'strip', 0], ['Box 60 tab', 'box', 90000]]],
            ],
        ]);
    }

    private static function itemsAntiseptikKulitKronis(): array
    {
        return self::buildItems([
            'antiseptik-luka' => [
                ['Betadine Antiseptik 30ml', 'betadine-30ml', 'Antiseptik povidone iodine untuk luka dan infeksi kulit.', 18000, [['Botol 30 ml', 'botol-30', 0], [self::BOT60ML, 'botol-60', 20000]]],
                ['Alkohol 70% Swab', 'alkohol-swab', 'Tisu alkohol untuk disinfeksi kulit sebelum injeksi.', 12000, [[self::B100PCS, 'box-100', 0], ['Box 200 pcs', 'box-200', 18000]]],
                ['Hydrogen Peroxide 3%', 'h2o2-3pct', 'Antiseptik pembersih luka dengan efek foaming.', 15000, [[self::BOT100ML, 'botol-100', 0], [self::BOT200ML, 'botol-200', 18000]]],
                ['Rivanol Antiseptik', 'rivanol', 'Antiseptik ringan untuk membersihkan luka infeksi.', 10000, [[self::BOT100ML, 'botol-100', 0], [self::BOT200ML, 'botol-200', 12000]]],
                ['Kasa Steril 16x16', 'kasa-steril', 'Kasa medis steril untuk penutup luka.', 8000, [['Pack 10 pcs', 'pack-10', 0], [self::B100PCS, 'box-100', 55000]]],
                ['Plester Elastis', 'plester-elastis', 'Plester luka fleksibel untuk berbagai ukuran luka.', 12000, [['Strip 10 pcs', 'strip-10', 0], [self::B100PCS, 'box-100', 85000]]],
                ['Perban Elastis 5cm', 'perban-elastis', 'Balutan elastis untuk sprain, strain, dan luka besar.', 15000, [['Gulung 5 cm', 'gulung-5', 0], ['Gulung 10 cm', 'gulung-10', 10000]]],
                ['Salep Antibiotik Neomycin', 'salep-neomycin', 'Salep antibiotik topikal untuk infeksi kulit dan luka bakar ringan.', 20000, [[self::T5GR, 'tube-5', 0], [self::T15GR, 'tube-15', 20000]]],
                ['Chlorhexidine Gel', 'chlorhexidine-gel', 'Antiseptik gel untuk perawatan luka akut.', 25000, [[self::T30GR, 'tube-30', 0], ['Tube 100 gr', 'tube-100', 45000]]],
                ['Povidone Iodine Salep', 'povidone-salep', 'Salep antiseptik iodine untuk luka bakar dan infeksi kulit.', 18000, [[self::T10GR, 'tube-10', 0], [self::T30GR, 'tube-30', 28000]]],
            ],
            'kulit-alergi' => [
                ['Hydrocortisone Cream 1%', 'hydrocortisone-1pct', 'Kortikosteroid topikal ringan untuk gatal dan inflamasi kulit.', 15000, [[self::T5GR, 'tube-5', 0], [self::T15GR, 'tube-15', 22000]]],
                ['Calamine Lotion', 'calamine-lotion', 'Losion pelega gatal akibat alergi, cacar air, dan gigitan serangga.', 18000, [[self::BOT60ML, 'botol-60', 0], [self::BOT120ML, 'botol-120', 18000]]],
                ['Miconazole Cream 2%', 'miconazole-cream', 'Antijamur topikal untuk kutu air, panu, dan kadas.', 18000, [[self::T10GR, 'tube-10', 0], [self::T30GR, 'tube-30', 35000]]],
                ['Ketoconazole Cream 2%', 'ketoconazole-cream', 'Antijamur spektrum luas untuk infeksi jamur kulit.', 20000, [[self::T10GR, 'tube-10', 0], [self::T30GR, 'tube-30', 40000]]],
                ['Clotrimazole Cream 1%', 'clotrimazole-cream', 'Antijamur topikal untuk kandidiasis dan dermatofitosis.', 15000, [[self::T10GR, 'tube-10', 0], ['Tube 20 gr', 'tube-20', 18000]]],
                ['Mometasone Cream 0.1%', 'mometasone-cream', 'Kortikosteroid topikal potensi sedang untuk eksema dan psoriasis.', 30000, [[self::T5GR, 'tube-5', 0], [self::T15GR, 'tube-15', 45000]]],
                ['Salep 2-4', 'salep-2-4', 'Kombinasi sulfur dan asam salisilat untuk panu dan jerawat.', 12000, [[self::T10GR, 'tube-10', 0], [self::T30GR, 'tube-30', 22000]]],
                ['Urea Cream 10%', 'urea-cream-10pct', 'Emolien untuk kulit kering dan bersisik.', 25000, [[self::T30GR, 'tube-30', 0], ['Tube 100 gr', 'tube-100', 40000]]],
                ['Salicylic Acid Gel 2%', 'salicylic-acid-gel', 'Keratolit topikal untuk jerawat dan kulit bersisik.', 20000, [[self::T15GR, 'tube-15', 0], [self::T30GR, 'tube-30', 18000]]],
                ['Betamethasone Cream', 'betamethasone-cream', 'Kortikosteroid potensi tinggi untuk dermatitis berat.', 22000, [[self::T5GR, 'tube-5', 0], [self::T10GR, 'tube-10', 18000]]],
            ],
            'penyakit-kronis' => [
                ['Metformin 500mg', 'metformin-500mg', 'Antidiabetes oral lini pertama untuk diabetes tipe 2.', 8000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 55000]]],
                ['Glibenclamide 5mg', 'glibenclamide-5mg', 'Sulfonilurea untuk menurunkan gula darah pada diabetes tipe 2.', 5000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 35000]]],
                ['Amlodipine 5mg', 'amlodipine-5mg', 'Calcium channel blocker untuk hipertensi dan angina.', 10000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 70000]]],
                ['Captopril 25mg', 'captopril-25mg', 'ACE inhibitor untuk hipertensi dan gagal jantung.', 8000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 55000]]],
                ['Atorvastatin 20mg', 'atorvastatin-20mg', 'Statin untuk menurunkan kolesterol LDL dan trigliserida.', 18000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 130000]]],
                ['Simvastatin 20mg', 'simvastatin-20mg', 'Statin untuk hiperkolesterolemia dan risiko kardiovaskular.', 12000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 85000]]],
                ['Lisinopril 10mg', 'lisinopril-10mg', 'ACE inhibitor untuk hipertensi dan perlindungan ginjal.', 12000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 85000]]],
                ['Bisoprolol 5mg', 'bisoprolol-5mg', 'Beta blocker untuk hipertensi, gagal jantung, dan aritmia.', 15000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 110000]]],
                ['Glimepiride 2mg', 'glimepiride-2mg', 'Sulfonilurea generasi ketiga untuk diabetes tipe 2.', 15000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 110000]]],
                ['Irbesartan 150mg', 'irbesartan-150mg', 'ARB untuk hipertensi dan nefropati diabetik.', 22000, [[self::S10TAB, 'strip', 0], ['Box 28 tab', 'box', 52000]]],
            ],
        ]);
    }

    private static function itemsAlatIbuKebersihan(): array
    {
        return self::buildItems([
            'alat-kesehatan' => [
                ['Tensimeter Digital', 'tensimeter-digital', 'Alat ukur tekanan darah lengan atas otomatis.', 180000, [['Standar', 'standar', 0], ['Large Cuff', 'large', 30000]]],
                ['Termometer Digital', 'termometer-digital', 'Termometer digital akurat untuk pengukuran suhu tubuh.', 45000, [['Aksila', 'aksila', 0], ['Telinga IR', 'telinga', 75000]]],
                ['Pulse Oximeter', 'pulse-oximeter', 'Alat ukur saturasi oksigen darah dan denyut nadi.', 85000, [['Standar', 'standar', 0], ['Pediatric', 'pediatric', 30000]]],
                ['Glucometer Set', 'glucometer-set', 'Alat ukur gula darah lengkap dengan lancet dan strip.', 250000, [['Basic', 'basic', 0], ['Plus Memori', 'plus', 80000]]],
                ['Strip Gula Darah', 'strip-gula-darah', 'Strip tes gula darah kompatibel berbagai merek glucometer.', 55000, [['Box 25 pcs', 'box-25', 0], ['Box 50 pcs', 'box-50', 90000]]],
                ['Nebulizer Compressor', 'nebulizer', 'Alat inhalasi untuk asma dan gangguan pernapasan.', 350000, [['Standard', 'standard', 0], ['Travel Size', 'travel', 50000]]],
                ['Masker Medis 3 Ply', 'masker-medis-3ply', 'Masker bedah tiga lapis pelindung pernapasan.', 25000, [['Box 50 pcs', 'box-50', 0], [self::B100PCS, 'box-100', 35000]]],
                ['Sarung Tangan Latex', 'sarung-tangan-latex', 'Sarung tangan medis steril untuk tindakan klinis.', 35000, [['Box 50 pcs S/M', 'box-50', 0], ['Box 100 pcs L/XL', 'box-100', 50000]]],
                ['Lancet Pen', 'lancet-pen', 'Alat penusuk jari untuk pengambilan sampel darah kapiler.', 30000, [['Pen + 10 Lancet', 'starter', 0], ['Box 100 Lancet', 'lancet-100', 40000]]],
                ['Timbangan Badan Digital', 'timbangan-badan', 'Timbangan badan digital presisi untuk monitoring berat badan.', 120000, [['Standar 150 kg', 'standar', 0], ['Smart BMI', 'smart', 80000]]],
            ],
            'ibu-anak' => [
                ['Paracetamol Syrup Anak', 'paracetamol-syrup-anak', 'Pereda demam dan nyeri untuk bayi dan anak-anak.', 22000, [[self::BOT60ML, 'botol-60', 0], [self::BOT120ML, 'botol-120', 15000]]],
                ['Multivitamin Anak Sirup', 'multivitamin-anak', 'Vitamin lengkap untuk tumbuh kembang anak.', 35000, [[self::BOT60ML, 'botol-60', 0], [self::BOT120ML, 'botol-120', 20000]]],
                ['Ibuprofen Syrup Anak', 'ibuprofen-syrup-anak', 'Antiinflamasi dan pereda demam untuk anak di atas 6 bulan.', 28000, [[self::BOT60ML, 'botol-60', 0], [self::BOT120ML, 'botol-120', 18000]]],
                ['Zinc Syrup Anak', 'zinc-syrup-anak', 'Suplemen zinc untuk diare anak dan tumbuh kembang.', 25000, [[self::BOT60ML, 'botol-60', 0], [self::BOT120ML, 'botol-120', 18000]]],
                ['Vitamin A 100.000 IU', 'vitamin-a-100000iu', 'Suplemen vitamin A untuk kesehatan mata dan imun anak.', 5000, [['Kapsul lunak', 'kapsul', 0], ['Box 10 kap', 'box-10', 30000]]],
                ['Probiotik Anak Sachet', 'probiotik-anak', 'Probiotik untuk kesehatan pencernaan bayi dan anak.', 30000, [[self::B10SACHET, 'box-10', 0], ['Box 30 sachet', 'box-30', 65000]]],
                ['Oralit Rasa Jeruk Anak', 'oralit-anak-jeruk', 'Larutan rehidrasi oral rasa jeruk untuk diare anak.', 5000, [['Sachet 200 ml', 'sachet', 0], [self::B10SACHET, 'box-10', 35000]]],
                ['Minyak Telon Plus', 'minyak-telon-plus', 'Minyak bayi penghangat untuk bayi dan anak kecil.', 22000, [[self::BOT60ML, 'botol-60', 0], ['Botol 150 ml', 'botol-150', 20000]]],
                ['DHA Anak Kapsul Lunak', 'dha-anak', 'Suplemen DHA untuk perkembangan otak dan kecerdasan anak.', 40000, [[self::S10KAP, 'strip-10', 0], [self::B60KAP, 'box-60', 180000]]],
                ['Asam Folat Ibu Hamil', 'asam-folat-bumil', 'Suplemen folat penting untuk ibu hamil trimester pertama.', 18000, [[self::S10TAB, 'strip', 0], [self::B100TAB, 'box', 130000]]],
            ],
            'kebersihan-personal-care' => [
                ['Hand Sanitizer Gel 60ml', 'hand-sanitizer-60ml', 'Pembersih tangan berbasis alkohol 70% tanpa bilas.', 15000, [[self::BOT60ML, 'botol-60', 0], ['Botol 500 ml', 'botol-500', 40000]]],
                ['Sabun Antiseptik Cair', 'sabun-antiseptik-cair', 'Sabun cuci tangan antibakteri untuk kebersihan optimal.', 20000, [['Botol 250 ml', 'botol-250', 0], ['Botol 1 Liter', 'botol-1l', 50000]]],
                ['Tisu Antiseptik Basah', 'tisu-antiseptik', 'Tisu basah mengandung antiseptik untuk kebersihan praktis.', 18000, [['Pack 30 lembar', 'pack-30', 0], ['Pack 80 lembar', 'pack-80', 22000]]],
                ['Masker KN95', 'masker-kn95', 'Masker penyaring partikel halus standar KN95/N95.', 15000, [['Pcs satuan', 'pcs', 0], ['Box 10 pcs', 'box-10', 100000]]],
                ['Kapas Medis 100gr', 'kapas-medis', 'Kapas higienis untuk perawatan luka dan kebersihan.', 12000, [['Pack 100 gr', 'pack-100', 0], ['Pack 250 gr', 'pack-250', 22000]]],
                ['Cotton Bud Medis', 'cotton-bud-medis', 'Cotton bud steril untuk kebersihan telinga dan perawatan luka.', 10000, [[self::B100PCS, 'box-100', 0], ['Box 200 pcs', 'box-200', 12000]]],
                ['Sabun Bayi', 'sabun-bayi', 'Sabun mandi lembut khusus bayi, bebas alkohol dan pewangi keras.', 18000, [['Bar 80 gr', 'bar', 0], ['Cair 200 ml', 'cair', 10000]]],
                ['Krim Tabir Surya SPF30', 'sunscreen-spf30', 'Pelindung kulit dari paparan sinar UV SPF 30.', 45000, [['Tube 30 ml', 'tube-30', 0], ['Tube 60 ml', 'tube-60', 30000]]],
                ['Facial Wash Medis', 'facial-wash-medis', 'Pembersih wajah hypoallergenic untuk kulit sensitif.', 35000, [['Tube 50 ml', 'tube-50', 0], ['Tube 100 ml', 'tube-100', 25000]]],
                ['Alkohol 70% Botol', 'alkohol-70pct', 'Alkohol isopropil 70% untuk disinfeksi permukaan dan peralatan.', 20000, [[self::BOT100ML, 'botol-100', 0], ['Botol 500 ml', 'botol-500', 55000]]],
            ],
        ]);
    }
}
