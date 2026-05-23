<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class MeatVeggieTemplatePlugin implements PluginInterface
{
    public function getName(): string    { return 'Meat & Veggie Template'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'KopiBot Team'; }

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
                'url'   => '/dashboard/super/meat-veggie-template.php',
                'icon'  => '🥩',
                'label' => 'Meat & Veggie Template',
            ];
        }

        return $items;
    }

    // ── Seed Data ────────────────────────────────────────────────

    /** Each entry: [name, slug, description, sort_order] */
    public static function getCategories(): array
    {
        return [
            ['Daging Sapi',         'daging-sapi',         'Aneka potongan daging sapi segar pilihan',               1],
            ['Ayam & Unggas',       'ayam-unggas',         'Daging ayam kampung, broiler, bebek segar harian',       2],
            ['Kambing & Domba',     'kambing-domba',       'Daging kambing dan domba segar berkualitas',             3],
            ['Sayuran Hijau',       'sayuran-hijau',       'Sayuran hijau segar dipanen setiap hari',                4],
            ['Sayuran Umbi & Buah', 'sayuran-umbi-buah',  'Umbi-umbian dan sayuran buah segar pilihan',             5],
            ['Bumbu & Rempah',      'bumbu-rempah',        'Bumbu dan rempah segar untuk masakan lezat',             6],
        ];
    }

    /**
     * Each item: [name, slug, description, price (IDR), sort_order, variants[]]
     * Each variant: [label, slug, price_delta (IDR), sort_order]
     */
    public static function getMenuItems(): array
    {
        return [
            // ── Daging Sapi (15) ──────────────────────────────────
            'daging-sapi' => [
                [
                    'name' => 'Daging Sapi Has Dalam', 'slug' => 'daging-sapi-has-dalam',
                    'desc' => 'Tenderloin sapi premium, empuk dan cocok untuk steak.',
                    'price' => 150000, 'sort' => 1,
                    'variants' => [
                        ['500gr', '500gr', 0,      1],
                        ['1kg',   '1kg',   140000,  2],
                    ],
                ],
                [
                    'name' => 'Daging Sapi Has Luar', 'slug' => 'daging-sapi-has-luar',
                    'desc' => 'Sirloin sapi berlemak tipis, juicy dan gurih.',
                    'price' => 130000, 'sort' => 2,
                    'variants' => [
                        ['500gr', '500gr', 0,      1],
                        ['1kg',   '1kg',   120000,  2],
                    ],
                ],
                [
                    'name' => 'Daging Sapi Iga', 'slug' => 'daging-sapi-iga',
                    'desc' => 'Iga sapi berdaging tebal, sempurna untuk sop dan bakar.',
                    'price' => 120000, 'sort' => 3,
                    'variants' => [
                        ['500gr', '500gr', 0,      1],
                        ['1kg',   '1kg',   110000,  2],
                    ],
                ],
                [
                    'name' => 'Daging Sapi Sengkel', 'slug' => 'daging-sapi-sengkel',
                    'desc' => 'Sengkel sapi berurat, cocok untuk semur dan rendang.',
                    'price' => 90000, 'sort' => 4,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   82000,  2],
                    ],
                ],
                [
                    'name' => 'Daging Sapi Gandik', 'slug' => 'daging-sapi-gandik',
                    'desc' => 'Gandik (round) sapi, cocok untuk empal dan abon.',
                    'price' => 100000, 'sort' => 5,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   92000,  2],
                    ],
                ],
                [
                    'name' => 'Daging Sapi Tetelan', 'slug' => 'daging-sapi-tetelan',
                    'desc' => 'Tetelan sapi berlemak, cocok untuk rawon dan soto.',
                    'price' => 75000, 'sort' => 6,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   68000,  2],
                    ],
                ],
                [
                    'name' => 'Daging Sapi Giling', 'slug' => 'daging-sapi-giling',
                    'desc' => 'Daging sapi giling halus, siap untuk bakso dan perkedel.',
                    'price' => 85000, 'sort' => 7,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   78000,  2],
                    ],
                ],
                [
                    'name' => 'Daging Sapi Cincang', 'slug' => 'daging-sapi-cincang',
                    'desc' => 'Daging sapi cincang kasar untuk isian martabak dan kebab.',
                    'price' => 80000, 'sort' => 8,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   73000,  2],
                    ],
                ],
                [
                    'name' => 'Lidah Sapi', 'slug' => 'lidah-sapi',
                    'desc' => 'Lidah sapi segar, lembut dan gurih untuk semur lidah.',
                    'price' => 110000, 'sort' => 9,
                    'variants' => [
                        ['500gr', '500gr', 0,      1],
                        ['1kg',   '1kg',   102000,  2],
                    ],
                ],
                [
                    'name' => 'Hati Sapi', 'slug' => 'hati-sapi',
                    'desc' => 'Hati sapi segar kaya zat besi, untuk tumis dan sate.',
                    'price' => 60000, 'sort' => 10,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   55000,  2],
                    ],
                ],
                [
                    'name' => 'Babat Sapi', 'slug' => 'babat-sapi',
                    'desc' => 'Babat sapi bersih, cocok untuk soto babat dan gule.',
                    'price' => 55000, 'sort' => 11,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   50000,  2],
                    ],
                ],
                [
                    'name' => 'Tulang Sapi', 'slug' => 'tulang-sapi',
                    'desc' => 'Tulang sapi sumsum untuk kaldu, sop, dan sup buntut.',
                    'price' => 45000, 'sort' => 12,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   42000,  2],
                    ],
                ],
                [
                    'name' => 'Otak Sapi', 'slug' => 'otak-sapi',
                    'desc' => 'Otak sapi segar, cocok untuk perkedel otak dan sate otak.',
                    'price' => 80000, 'sort' => 13,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   74000,  2],
                    ],
                ],
                [
                    'name' => 'Kikil Sapi', 'slug' => 'kikil-sapi',
                    'desc' => 'Kikil sapi bersih kenyal, cocok untuk soto dan gule kikil.',
                    'price' => 60000, 'sort' => 14,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   55000,  2],
                    ],
                ],
                [
                    'name' => 'Daging Wagyu Lokal', 'slug' => 'daging-wagyu-lokal',
                    'desc' => 'Wagyu lokal marbling tinggi, meleleh sempurna di mulut.',
                    'price' => 250000, 'sort' => 15,
                    'variants' => [
                        ['500gr', '500gr', 0,      1],
                        ['1kg',   '1kg',   235000,  2],
                    ],
                ],
            ],

            // ── Ayam & Unggas (15) ────────────────────────────────
            'ayam-unggas' => [
                [
                    'name' => 'Ayam Broiler Utuh', 'slug' => 'ayam-broiler-utuh',
                    'desc' => 'Ayam broiler segar utuh siap potong, ±1.2–1.5kg/ekor.',
                    'price' => 35000, 'sort' => 1, 'variants' => [],
                ],
                [
                    'name' => 'Ayam Kampung Utuh', 'slug' => 'ayam-kampung-utuh',
                    'desc' => 'Ayam kampung segar, lebih gurih dan padat daging.',
                    'price' => 75000, 'sort' => 2, 'variants' => [],
                ],
                [
                    'name' => 'Dada Ayam Fillet', 'slug' => 'dada-ayam-fillet',
                    'desc' => 'Dada ayam tanpa kulit tanpa tulang, tinggi protein rendah lemak.',
                    'price' => 40000, 'sort' => 3,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   37000,  2],
                    ],
                ],
                [
                    'name' => 'Paha Ayam Atas', 'slug' => 'paha-ayam-atas',
                    'desc' => 'Paha atas ayam (thigh), berdaging tebal juicy.',
                    'price' => 35000, 'sort' => 4,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   32000,  2],
                    ],
                ],
                [
                    'name' => 'Paha Ayam Bawah', 'slug' => 'paha-ayam-bawah',
                    'desc' => 'Drumstick ayam, cocok untuk digoreng dan dibakar.',
                    'price' => 30000, 'sort' => 5,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   27000,  2],
                    ],
                ],
                [
                    'name' => 'Sayap Ayam', 'slug' => 'sayap-ayam',
                    'desc' => 'Sayap ayam segar, favorit untuk buffalo wings dan bakar.',
                    'price' => 28000, 'sort' => 6,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   25000,  2],
                    ],
                ],
                [
                    'name' => 'Ceker Ayam', 'slug' => 'ceker-ayam',
                    'desc' => 'Ceker ayam segar, cocok untuk dimsum dan soto.',
                    'price' => 20000, 'sort' => 7,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   18000,  2],
                    ],
                ],
                [
                    'name' => 'Hati Ayam', 'slug' => 'hati-ayam',
                    'desc' => 'Hati ayam segar, cocok untuk sate dan semur hati.',
                    'price' => 22000, 'sort' => 8,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   20000,  2],
                    ],
                ],
                [
                    'name' => 'Ampela Ayam', 'slug' => 'ampela-ayam',
                    'desc' => 'Ampela ayam kenyal, lezat untuk tumis dan sate ampela.',
                    'price' => 22000, 'sort' => 9,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   20000,  2],
                    ],
                ],
                [
                    'name' => 'Ayam Fillet Tanpa Tulang', 'slug' => 'ayam-fillet-tanpa-tulang',
                    'desc' => 'Fillet ayam utuh tanpa tulang siap masak, premium.',
                    'price' => 55000, 'sort' => 10,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   50000,  2],
                    ],
                ],
                [
                    'name' => 'Kulit Ayam', 'slug' => 'kulit-ayam',
                    'desc' => 'Kulit ayam segar untuk kerupuk, krispi, dan kuah.',
                    'price' => 20000, 'sort' => 11,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   18000,  2],
                    ],
                ],
                [
                    'name' => 'Kepala Ayam', 'slug' => 'kepala-ayam',
                    'desc' => 'Kepala ayam segar untuk kaldu dan soto.',
                    'price' => 15000, 'sort' => 12,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   13000,  2],
                    ],
                ],
                [
                    'name' => 'Bebek Utuh', 'slug' => 'bebek-utuh',
                    'desc' => 'Bebek segar utuh ±1.5kg, cocok untuk bebek goreng dan peking.',
                    'price' => 80000, 'sort' => 13, 'variants' => [],
                ],
                [
                    'name' => 'Daging Bebek Fillet', 'slug' => 'daging-bebek-fillet',
                    'desc' => 'Fillet bebek tanpa tulang, gurih dan berdaging tebal.',
                    'price' => 70000, 'sort' => 14,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   64000,  2],
                    ],
                ],
                [
                    'name' => 'Telur Ayam Kampung', 'slug' => 'telur-ayam-kampung',
                    'desc' => 'Telur ayam kampung segar, kuning telur lebih kaya dan padat.',
                    'price' => 35000, 'sort' => 15,
                    'variants' => [
                        ['6 Butir',  '6-butir',  0,     1],
                        ['12 Butir', '12-butir', 30000,  2],
                    ],
                ],
            ],

            // ── Kambing & Domba (10) ──────────────────────────────
            'kambing-domba' => [
                [
                    'name' => 'Daging Kambing Giling', 'slug' => 'daging-kambing-giling',
                    'desc' => 'Daging kambing giling halus, untuk kebab dan bakso kambing.',
                    'price' => 90000, 'sort' => 1,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   82000,  2],
                    ],
                ],
                [
                    'name' => 'Daging Kambing Iga', 'slug' => 'daging-kambing-iga',
                    'desc' => 'Iga kambing segar berdaging tebal, untuk gule dan sate.',
                    'price' => 95000, 'sort' => 2,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   87000,  2],
                    ],
                ],
                [
                    'name' => 'Daging Kambing Paha', 'slug' => 'daging-kambing-paha',
                    'desc' => 'Paha kambing berdaging banyak, cocok untuk rendang dan panggang.',
                    'price' => 100000, 'sort' => 3,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   92000,  2],
                    ],
                ],
                [
                    'name' => 'Daging Kambing Tulang Muda', 'slug' => 'daging-kambing-tulang-muda',
                    'desc' => 'Tulang muda kambing, empuk dan gurih untuk sop kacang.',
                    'price' => 80000, 'sort' => 4,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   73000,  2],
                    ],
                ],
                [
                    'name' => 'Hati Kambing', 'slug' => 'hati-kambing',
                    'desc' => 'Hati kambing segar, cocok untuk sate dan tumis pedas.',
                    'price' => 65000, 'sort' => 5,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   59000,  2],
                    ],
                ],
                [
                    'name' => 'Jeroan Kambing Mix', 'slug' => 'jeroan-kambing-mix',
                    'desc' => 'Campuran jeroan kambing segar, untuk gule jeroan.',
                    'price' => 70000, 'sort' => 6,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   64000,  2],
                    ],
                ],
                [
                    'name' => 'Kepala Kambing', 'slug' => 'kepala-kambing',
                    'desc' => 'Kepala kambing utuh segar, kaya gelatin untuk soto.',
                    'price' => 120000, 'sort' => 7, 'variants' => [],
                ],
                [
                    'name' => 'Daging Domba Rack', 'slug' => 'daging-domba-rack',
                    'desc' => 'Rack of lamb premium, empuk dan beraroma khas.',
                    'price' => 130000, 'sort' => 8,
                    'variants' => [
                        ['500gr', '500gr', 0,      1],
                        ['1kg',   '1kg',   120000,  2],
                    ],
                ],
                [
                    'name' => 'Daging Domba Shoulder', 'slug' => 'daging-domba-shoulder',
                    'desc' => 'Bahu domba berdaging tebal, ideal untuk slow-cook dan panggang.',
                    'price' => 110000, 'sort' => 9,
                    'variants' => [
                        ['500gr', '500gr', 0,      1],
                        ['1kg',   '1kg',   101000,  2],
                    ],
                ],
                [
                    'name' => 'Sosis Kambing', 'slug' => 'sosis-kambing',
                    'desc' => 'Sosis kambing handmade berbumbu rempah khas Timur Tengah.',
                    'price' => 55000, 'sort' => 10,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 48000,  2],
                    ],
                ],
            ],

            // ── Sayuran Hijau (20) ────────────────────────────────
            'sayuran-hijau' => [
                [
                    'name' => 'Bayam Hijau', 'slug' => 'bayam-hijau',
                    'desc' => 'Bayam hijau segar kaya zat besi, untuk tumis dan sop.',
                    'price' => 8000, 'sort' => 1,
                    'variants' => [
                        ['250gr', '250gr', 0,    1],
                        ['500gr', '500gr', 7000,  2],
                    ],
                ],
                [
                    'name' => 'Kangkung', 'slug' => 'kangkung',
                    'desc' => 'Kangkung segar renyah, favorit untuk tumis belacan.',
                    'price' => 7000, 'sort' => 2,
                    'variants' => [
                        ['250gr', '250gr', 0,    1],
                        ['500gr', '500gr', 6000,  2],
                    ],
                ],
                [
                    'name' => 'Sawi Hijau', 'slug' => 'sawi-hijau',
                    'desc' => 'Sawi hijau segar, lezat untuk tumis dan mie ayam.',
                    'price' => 8000, 'sort' => 3,
                    'variants' => [
                        ['250gr', '250gr', 0,    1],
                        ['500gr', '500gr', 7000,  2],
                    ],
                ],
                [
                    'name' => 'Sawi Putih', 'slug' => 'sawi-putih',
                    'desc' => 'Sawi putih (napa cabbage) segar, renyah untuk sup.',
                    'price' => 10000, 'sort' => 4,
                    'variants' => [
                        ['500gr', '500gr', 0,    1],
                        ['1kg',   '1kg',   9000,  2],
                    ],
                ],
                [
                    'name' => 'Brokoli', 'slug' => 'brokoli',
                    'desc' => 'Brokoli hijau segar kaya vitamin C dan serat.',
                    'price' => 18000, 'sort' => 5,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   16000,  2],
                    ],
                ],
                [
                    'name' => 'Kembang Kol', 'slug' => 'kembang-kol',
                    'desc' => 'Kembang kol putih segar, rendah kalori dan kaya nutrisi.',
                    'price' => 15000, 'sort' => 6,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   14000,  2],
                    ],
                ],
                [
                    'name' => 'Kubis Bulat', 'slug' => 'kubis-bulat',
                    'desc' => 'Kubis (kol) bulat segar, renyah untuk lalapan dan tumis.',
                    'price' => 12000, 'sort' => 7,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   11000,  2],
                    ],
                ],
                [
                    'name' => 'Selada Hijau', 'slug' => 'selada-hijau',
                    'desc' => 'Selada hijau segar renyah, untuk salad dan burger.',
                    'price' => 12000, 'sort' => 8,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 11000,  2],
                    ],
                ],
                [
                    'name' => 'Daun Singkong', 'slug' => 'daun-singkong',
                    'desc' => 'Daun singkong segar untuk gulai daun singkong.',
                    'price' => 5000, 'sort' => 9, 'variants' => [],
                ],
                [
                    'name' => 'Daun Pepaya', 'slug' => 'daun-pepaya',
                    'desc' => 'Daun pepaya muda segar, untuk tumis dan lalapan.',
                    'price' => 5000, 'sort' => 10, 'variants' => [],
                ],
                [
                    'name' => 'Buncis', 'slug' => 'buncis',
                    'desc' => 'Buncis muda segar renyah, untuk cap cay dan tumis.',
                    'price' => 12000, 'sort' => 11,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 11000,  2],
                    ],
                ],
                [
                    'name' => 'Kacang Panjang', 'slug' => 'kacang-panjang',
                    'desc' => 'Kacang panjang segar muda, untuk sayur lodeh dan tumis.',
                    'price' => 10000, 'sort' => 12,
                    'variants' => [
                        ['250gr', '250gr', 0,    1],
                        ['500gr', '500gr', 9000,  2],
                    ],
                ],
                [
                    'name' => 'Ketimun', 'slug' => 'ketimun',
                    'desc' => 'Ketimun segar renyah berair, untuk lalapan dan acar.',
                    'price' => 8000, 'sort' => 13,
                    'variants' => [
                        ['250gr', '250gr', 0,    1],
                        ['500gr', '500gr', 7000,  2],
                    ],
                ],
                [
                    'name' => 'Terong Ungu', 'slug' => 'terong-ungu',
                    'desc' => 'Terong ungu segar, cocok untuk balado dan tumis.',
                    'price' => 10000, 'sort' => 14,
                    'variants' => [
                        ['500gr', '500gr', 0,    1],
                        ['1kg',   '1kg',   9000,  2],
                    ],
                ],
                [
                    'name' => 'Cabai Merah Keriting', 'slug' => 'cabai-merah-keriting',
                    'desc' => 'Cabai merah keriting segar, pedas sedang untuk sambal.',
                    'price' => 30000, 'sort' => 15,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 27000,  2],
                    ],
                ],
                [
                    'name' => 'Cabai Rawit Hijau', 'slug' => 'cabai-rawit-hijau',
                    'desc' => 'Cabai rawit hijau segar, ekstra pedas untuk sambal.',
                    'price' => 35000, 'sort' => 16,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 32000,  2],
                    ],
                ],
                [
                    'name' => 'Daun Kemangi', 'slug' => 'daun-kemangi',
                    'desc' => 'Daun kemangi segar harum, untuk lalapan dan pepes.',
                    'price' => 5000, 'sort' => 17, 'variants' => [],
                ],
                [
                    'name' => 'Daun Pandan', 'slug' => 'daun-pandan',
                    'desc' => 'Daun pandan segar harum, untuk masakan dan kue.',
                    'price' => 5000, 'sort' => 18, 'variants' => [],
                ],
                [
                    'name' => 'Daun Seledri', 'slug' => 'daun-seledri',
                    'desc' => 'Seledri segar harum, untuk sop dan pelengkap masakan.',
                    'price' => 6000, 'sort' => 19, 'variants' => [],
                ],
                [
                    'name' => 'Peterseli', 'slug' => 'peterseli',
                    'desc' => 'Peterseli segar untuk garnish dan salad Mediterania.',
                    'price' => 8000, 'sort' => 20, 'variants' => [],
                ],
            ],

            // ── Sayuran Umbi & Buah (12) ──────────────────────────
            'sayuran-umbi-buah' => [
                [
                    'name' => 'Wortel', 'slug' => 'wortel',
                    'desc' => 'Wortel segar kaya beta-karoten, untuk sup dan jus.',
                    'price' => 12000, 'sort' => 1,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   11000,  2],
                    ],
                ],
                [
                    'name' => 'Kentang', 'slug' => 'kentang',
                    'desc' => 'Kentang segar pilihan, cocok untuk goreng dan rebus.',
                    'price' => 15000, 'sort' => 2,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   14000,  2],
                    ],
                ],
                [
                    'name' => 'Ubi Jalar Oranye', 'slug' => 'ubi-jalar-oranye',
                    'desc' => 'Ubi jalar oranye manis kaya antioksidan.',
                    'price' => 12000, 'sort' => 3,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   11000,  2],
                    ],
                ],
                [
                    'name' => 'Singkong', 'slug' => 'singkong',
                    'desc' => 'Singkong segar gurih untuk gorengan dan kolak.',
                    'price' => 8000, 'sort' => 4,
                    'variants' => [
                        ['500gr', '500gr', 0,    1],
                        ['1kg',   '1kg',   7000,  2],
                    ],
                ],
                [
                    'name' => 'Talas', 'slug' => 'talas',
                    'desc' => 'Talas segar untuk kolak, rebus, dan keripik talas.',
                    'price' => 12000, 'sort' => 5,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   11000,  2],
                    ],
                ],
                [
                    'name' => 'Tomat Merah', 'slug' => 'tomat-merah',
                    'desc' => 'Tomat merah matang segar, kaya likopen untuk sambal.',
                    'price' => 12000, 'sort' => 6,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   11000,  2],
                    ],
                ],
                [
                    'name' => 'Tomat Cherry', 'slug' => 'tomat-cherry',
                    'desc' => 'Tomat cherry merah manis, cantik untuk salad dan garnish.',
                    'price' => 20000, 'sort' => 7,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 18000,  2],
                    ],
                ],
                [
                    'name' => 'Paprika Merah', 'slug' => 'paprika-merah',
                    'desc' => 'Paprika merah manis renyah, untuk tumis dan salad.',
                    'price' => 25000, 'sort' => 8,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 23000,  2],
                    ],
                ],
                [
                    'name' => 'Paprika Kuning', 'slug' => 'paprika-kuning',
                    'desc' => 'Paprika kuning segar manis, cerah untuk masakan fusion.',
                    'price' => 25000, 'sort' => 9,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 23000,  2],
                    ],
                ],
                [
                    'name' => 'Jagung Manis', 'slug' => 'jagung-manis',
                    'desc' => 'Jagung manis segar, enak direbus dan dibakar.',
                    'price' => 8000, 'sort' => 10, 'variants' => [],
                ],
                [
                    'name' => 'Labu Kuning', 'slug' => 'labu-kuning',
                    'desc' => 'Labu kuning manis, untuk kolak dan sup krim.',
                    'price' => 15000, 'sort' => 11,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   14000,  2],
                    ],
                ],
                [
                    'name' => 'Lobak Putih', 'slug' => 'lobak-putih',
                    'desc' => 'Lobak putih segar, untuk acar, sup, dan dim sum.',
                    'price' => 10000, 'sort' => 12,
                    'variants' => [
                        ['500gr', '500gr', 0,    1],
                        ['1kg',   '1kg',   9000,  2],
                    ],
                ],
            ],

            // ── Bumbu & Rempah (8) ────────────────────────────────
            'bumbu-rempah' => [
                [
                    'name' => 'Bawang Merah', 'slug' => 'bawang-merah',
                    'desc' => 'Bawang merah lokal pilihan, dasar bumbu masakan Indonesia.',
                    'price' => 25000, 'sort' => 1,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   23000,  2],
                    ],
                ],
                [
                    'name' => 'Bawang Putih', 'slug' => 'bawang-putih',
                    'desc' => 'Bawang putih segar, wajib untuk hampir semua masakan.',
                    'price' => 30000, 'sort' => 2,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   28000,  2],
                    ],
                ],
                [
                    'name' => 'Jahe Segar', 'slug' => 'jahe-segar',
                    'desc' => 'Jahe segar harum, untuk masakan, jamu, dan minuman.',
                    'price' => 15000, 'sort' => 3,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 13000,  2],
                    ],
                ],
                [
                    'name' => 'Kunyit Segar', 'slug' => 'kunyit-segar',
                    'desc' => 'Kunyit segar kaya kurkumin, dasar bumbu kuning.',
                    'price' => 12000, 'sort' => 4,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 11000,  2],
                    ],
                ],
                [
                    'name' => 'Lengkuas Segar', 'slug' => 'lengkuas-segar',
                    'desc' => 'Lengkuas (galangal) segar harum, untuk rendang dan gulai.',
                    'price' => 12000, 'sort' => 5,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 11000,  2],
                    ],
                ],
                [
                    'name' => 'Kemiri', 'slug' => 'kemiri',
                    'desc' => 'Kemiri segar untuk bumbu halus kaya dan gurih.',
                    'price' => 20000, 'sort' => 6,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 18000,  2],
                    ],
                ],
                [
                    'name' => 'Ketumbar Biji', 'slug' => 'ketumbar-biji',
                    'desc' => 'Ketumbar biji segar beraroma, bumbu wajib opor dan soto.',
                    'price' => 18000, 'sort' => 7,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 16000,  2],
                    ],
                ],
                [
                    'name' => 'Daun Salam', 'slug' => 'daun-salam',
                    'desc' => 'Daun salam segar harum, penyedap semur dan gulai.',
                    'price' => 5000, 'sort' => 8, 'variants' => [],
                ],
            ],
        ];
    }

    // ── USD Price Map ────────────────────────────────────────────

    private static function getUsdPrices(): array
    {
        return [
            // Daging Sapi
            'daging-sapi-has-dalam'      => 9.50,
            'daging-sapi-has-luar'       => 8.00,
            'daging-sapi-iga'            => 7.50,
            'daging-sapi-sengkel'        => 5.50,
            'daging-sapi-gandik'         => 6.00,
            'daging-sapi-tetelan'        => 4.75,
            'daging-sapi-giling'         => 5.25,
            'daging-sapi-cincang'        => 5.00,
            'lidah-sapi'                 => 7.00,
            'hati-sapi'                  => 3.75,
            'babat-sapi'                 => 3.50,
            'tulang-sapi'                => 2.75,
            'otak-sapi'                  => 5.00,
            'kikil-sapi'                 => 3.75,
            'daging-wagyu-lokal'         => 15.00,
            // Ayam & Unggas
            'ayam-broiler-utuh'          => 2.25,
            'ayam-kampung-utuh'          => 4.75,
            'dada-ayam-fillet'           => 2.50,
            'paha-ayam-atas'             => 2.25,
            'paha-ayam-bawah'            => 1.75,
            'sayap-ayam'                 => 1.75,
            'ceker-ayam'                 => 1.25,
            'hati-ayam'                  => 1.50,
            'ampela-ayam'                => 1.50,
            'ayam-fillet-tanpa-tulang'   => 3.50,
            'kulit-ayam'                 => 1.25,
            'kepala-ayam'                => 0.95,
            'bebek-utuh'                 => 5.00,
            'daging-bebek-fillet'        => 4.50,
            'telur-ayam-kampung'         => 2.25,
            // Kambing & Domba
            'daging-kambing-giling'      => 5.75,
            'daging-kambing-iga'         => 6.00,
            'daging-kambing-paha'        => 6.25,
            'daging-kambing-tulang-muda' => 5.00,
            'hati-kambing'               => 4.00,
            'jeroan-kambing-mix'         => 4.50,
            'kepala-kambing'             => 7.50,
            'daging-domba-rack'          => 8.00,
            'daging-domba-shoulder'      => 7.00,
            'sosis-kambing'              => 3.50,
            // Sayuran Hijau
            'bayam-hijau'                => 0.75,
            'kangkung'                   => 0.65,
            'sawi-hijau'                 => 0.75,
            'sawi-putih'                 => 0.85,
            'brokoli'                    => 1.25,
            'kembang-kol'                => 1.00,
            'kubis-bulat'                => 0.85,
            'selada-hijau'               => 0.85,
            'daun-singkong'              => 0.50,
            'daun-pepaya'                => 0.50,
            'buncis'                     => 0.85,
            'kacang-panjang'             => 0.75,
            'ketimun'                    => 0.65,
            'terong-ungu'                => 0.75,
            'cabai-merah-keriting'       => 2.00,
            'cabai-rawit-hijau'          => 2.25,
            'daun-kemangi'               => 0.50,
            'daun-pandan'                => 0.50,
            'daun-seledri'               => 0.55,
            'peterseli'                  => 0.65,
            // Sayuran Umbi & Buah
            'wortel'                     => 0.85,
            'kentang'                    => 1.00,
            'ubi-jalar-oranye'           => 0.85,
            'singkong'                   => 0.65,
            'talas'                      => 0.85,
            'tomat-merah'                => 0.85,
            'tomat-cherry'               => 1.50,
            'paprika-merah'              => 1.75,
            'paprika-kuning'             => 1.75,
            'jagung-manis'               => 0.65,
            'labu-kuning'                => 1.00,
            'lobak-putih'                => 0.75,
            // Bumbu & Rempah
            'bawang-merah'               => 1.75,
            'bawang-putih'               => 2.00,
            'jahe-segar'                 => 1.00,
            'kunyit-segar'               => 0.85,
            'lengkuas-segar'             => 0.85,
            'kemiri'                     => 1.50,
            'ketumbar-biji'              => 1.25,
            'daun-salam'                 => 0.50,
        ];
    }

    private static function getCurrencyFactor(string $currency): float
    {
        return match (strtoupper($currency)) {
            'SGD'   => 1.35,
            'AUD'   => 1.55,
            'USD'   => 1.00,
            default => 1.00,
        };
    }

    // ── Reset & Seed ─────────────────────────────────────────────

    public function resetAndSeed(): array
    {
        $pdo = Database::getInstance();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $branchRows = $pdo->query(
            "SELECT b.id AS branch_id,
                    UPPER(COALESCE(bs.setting_val, 'IDR')) AS currency
             FROM branches b
             LEFT JOIN branch_settings bs
               ON bs.branch_id = b.id AND bs.setting_key = 'currency'
             WHERE b.is_active = 1"
        )->fetchAll();

        $nonIdrBranches = [];
        foreach ($branchRows as $row) {
            if ($row['currency'] !== 'IDR') {
                $nonIdrBranches[(int)$row['branch_id']] = $row['currency'];
            }
        }

        $usdPrices = self::getUsdPrices();

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
            $stmtItemOvr = $pdo->prepare(
                'INSERT INTO branch_menu_overrides
                 (branch_id, menu_item_id, custom_price, is_available)
                 VALUES (:branch_id, :item_id, :price, 1)'
            );
            $stmtVarOvr = $pdo->prepare(
                'INSERT INTO branch_menu_variant_overrides
                 (branch_id, variant_id, price_delta, is_active)
                 VALUES (:branch_id, :variant_id, :delta, 1)'
            );

            $categoryMap = [];
            foreach (self::getCategories() as [$name, $slug, $desc, $sort]) {
                $stmtCat->execute([':name' => $name, ':slug' => $slug, ':desc' => $desc, ':sort' => $sort]);
                $categoryMap[$slug] = (int)$pdo->lastInsertId();
            }

            $seededItems    = [];
            $seededVariants = [];

            foreach (self::getMenuItems() as $catSlug => $items) {
                $catId = $categoryMap[$catSlug] ?? null;
                if (!$catId) {
                    continue;
                }

                foreach ($items as $item) {
                    $idrBase = (float)$item['price'];
                    $usdBase = (float)($usdPrices[$item['slug']] ?? round($idrBase / 16000, 2));

                    $stmtItem->execute([
                        ':cat_id' => $catId,
                        ':name'   => $item['name'],
                        ':slug'   => $item['slug'],
                        ':desc'   => $item['desc'],
                        ':price'  => $idrBase,
                        ':sort'   => $item['sort'],
                    ]);
                    $itemId = (int)$pdo->lastInsertId();
                    $seededItems[$itemId] = ['idr' => $idrBase, 'usd' => $usdBase];

                    foreach ($item['variants'] as [$label, $vSlug, $idrDelta, $vSort]) {
                        $stmtVariant->execute([
                            ':item_id' => $itemId,
                            ':label'   => $label,
                            ':slug'    => $vSlug,
                            ':delta'   => $idrDelta,
                            ':sort'    => $vSort,
                        ]);
                        $variantId = (int)$pdo->lastInsertId();
                        $seededVariants[$variantId] = [
                            'idr_delta' => (float)$idrDelta,
                            'idr_base'  => $idrBase,
                            'usd_base'  => $usdBase,
                        ];
                    }
                }
            }

            foreach ($nonIdrBranches as $branchId => $currency) {
                $factor = self::getCurrencyFactor($currency);

                foreach ($seededItems as $itemId => $prices) {
                    $stmtItemOvr->execute([
                        ':branch_id' => $branchId,
                        ':item_id'   => $itemId,
                        ':price'     => round($prices['usd'] * $factor, 2),
                    ]);
                }

                foreach ($seededVariants as $variantId => $v) {
                    if ($v['idr_delta'] == 0.0) {
                        $convertedDelta = 0.0;
                    } else {
                        $ratio          = $v['idr_base'] > 0 ? ($v['idr_delta'] / $v['idr_base']) : 0.0;
                        $convertedDelta = round($ratio * $v['usd_base'] * $factor * 10) / 10;
                    }
                    $stmtVarOvr->execute([
                        ':branch_id'  => $branchId,
                        ':variant_id' => $variantId,
                        ':delta'      => $convertedDelta,
                    ]);
                }
            }

            $pdo->commit();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            return [
                'success'           => true,
                'categories'        => count($categoryMap),
                'items'             => count($seededItems),
                'variants'          => count($seededVariants),
                'override_branches' => $nonIdrBranches,
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
