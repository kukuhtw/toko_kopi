<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class RestoIndonesiaTemplatePlugin implements PluginInterface
{
    public function getName(): string    { return 'Resto Indonesia Template'; }
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
                'url'   => '/dashboard/super/indonesian-resto-template.php',
                'icon'  => 'RI',
                'label' => 'Resto Indonesia',
            ];
        }
        return $items;
    }

    // ── Categories ───────────────────────────────────────────────

    /** [name, slug, description, sort_order] */
    public static function getCategories(): array
    {
        return [
            ['Nasi & Olahan',               'nasi-olahan',          'Aneka nasi dan olahan nasi khas Nusantara',                   1],
            ['Lauk Pauk',                   'lauk-pauk',            'Lauk pauk tradisional dari berbagai daerah Indonesia',        2],
            ['Soto & Sup',                  'soto-sup',             'Soto dan sup berkuah kaya rempah khas Indonesia',             3],
            ['Mie & Bihun',                 'mie-bihun',            'Aneka olahan mie, bihun, dan kwetiau ala Nusantara',          4],
            ['Sate & Bakar',                'sate-bakar',           'Sate tusuk dan hidangan panggang bumbu khas daerah',          5],
            ['Pecel & Salad Tradisional',   'pecel-salad',          'Hidangan sayur segar dengan bumbu kacang dan rempah',         6],
            ['Camilan & Gorengan',          'camilan-gorengan',     'Cemilan gorengan dan jajanan pasar yang gurih',               7],
            ['Sayuran Tumis & Berkuah',     'sayuran-tumis',        'Olahan sayuran segar ditumis dan berkuah rempah',             8],
            ['Minuman Tradisional',         'minuman-tradisional',  'Minuman khas Nusantara hangat maupun dingin',                 9],
            ['Dessert Tradisional',         'dessert-tradisional',  'Jajanan manis dan dessert tradisional Nusantara',            10],
        ];
    }

    // ── Menu Items ───────────────────────────────────────────────

    /**
     * [name, slug, description, price (IDR), sort_order, variants[]]
     * Variant: [label, slug, price_delta (IDR), sort_order]
     */
    public static function getMenuItems(): array
    {
        return [

            // ════════════════════════════════════════════════════
            // 1. NASI & OLAHAN  (15 item)
            // ════════════════════════════════════════════════════
            'nasi-olahan' => [
                [
                    'name' => 'Nasi Goreng Kampung', 'slug' => 'nasi-goreng-kampung',
                    'desc' => 'Nasi goreng bumbu rumahan dengan kecap, bawang merah, cabai rawit, dan lalapan segar.',
                    'price' => 25000, 'sort' => 1,
                    'variants' => [
                        ['Original',    'original',    0,    1],
                        ['Pedas',       'pedas',       2000, 2],
                        ['Extra Pedas', 'extra-pedas', 4000, 3],
                    ],
                ],
                [
                    'name' => 'Nasi Goreng Spesial', 'slug' => 'nasi-goreng-spesial',
                    'desc' => 'Nasi goreng lengkap dengan telur ceplok, ayam suwir, bakso, dan acar timun.',
                    'price' => 32000, 'sort' => 2,
                    'variants' => [
                        ['Original',    'original',    0,    1],
                        ['Pedas',       'pedas',       2000, 2],
                        ['Extra Pedas', 'extra-pedas', 4000, 3],
                    ],
                ],
                [
                    'name' => 'Nasi Goreng Seafood', 'slug' => 'nasi-goreng-seafood',
                    'desc' => 'Nasi goreng dengan udang, cumi, dan sayuran segar dalam bumbu wajan panas.',
                    'price' => 38000, 'sort' => 3,
                    'variants' => [
                        ['Original',    'original',    0,    1],
                        ['Pedas Manis', 'pedas-manis', 2000, 2],
                        ['Extra Pedas', 'extra-pedas', 4000, 3],
                    ],
                ],
                [
                    'name' => 'Nasi Goreng Rendang', 'slug' => 'nasi-goreng-rendang',
                    'desc' => 'Nasi goreng dengan potongan rendang daging sapi kering yang kaya rempah.',
                    'price' => 42000, 'sort' => 4,
                    'variants' => [
                        ['Porsi Normal', 'porsi-normal', 0,     1],
                        ['Porsi Jumbo',  'porsi-jumbo',  10000, 2],
                    ],
                ],
                [
                    'name' => 'Nasi Uduk', 'slug' => 'nasi-uduk',
                    'desc' => 'Nasi dimasak dengan santan dan rempah, disajikan dengan lauk tempe orek dan kerupuk.',
                    'price' => 22000, 'sort' => 5,
                    'variants' => [
                        ['Porsi Kecil',  'porsi-kecil',  0,    1],
                        ['Porsi Normal', 'porsi-normal', 5000, 2],
                    ],
                ],
                [
                    'name' => 'Nasi Kuning Komplit', 'slug' => 'nasi-kuning-komplit',
                    'desc' => 'Nasi kuning kunyit harum dengan bihun goreng, telur balado, dan ayam suwir.',
                    'price' => 28000, 'sort' => 6,
                    'variants' => [
                        ['Porsi Kecil',  'porsi-kecil',  0,    1],
                        ['Porsi Normal', 'porsi-normal', 5000, 2],
                    ],
                ],
                [
                    'name' => 'Nasi Liwet Solo', 'slug' => 'nasi-liwet-solo',
                    'desc' => 'Nasi liwet khas Solo dengan santan, serai, daun salam, disajikan dengan opor dan sayur labu.',
                    'price' => 30000, 'sort' => 7,
                    'variants' => [],
                ],
                [
                    'name' => 'Nasi Bakar Ikan', 'slug' => 'nasi-bakar-ikan',
                    'desc' => 'Nasi dibungkus daun pisang dengan ikan tongkol pedas, dipanggang hingga harum.',
                    'price' => 28000, 'sort' => 8,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     2000, 2],
                    ],
                ],
                [
                    'name' => 'Nasi Padang', 'slug' => 'nasi-padang',
                    'desc' => 'Nasi Padang lengkap pilihan lauk, disajikan dengan rendang, gulai, dan sambal hijau.',
                    'price' => 45000, 'sort' => 9,
                    'variants' => [
                        ['Lauk Rendang',   'lauk-rendang',   5000,  1],
                        ['Lauk Ayam Pop',  'lauk-ayam-pop',  0,     2],
                        ['Lauk Gulai Ikan','lauk-gulai-ikan', 3000, 3],
                    ],
                ],
                [
                    'name' => 'Nasi Tim Ayam', 'slug' => 'nasi-tim-ayam',
                    'desc' => 'Nasi tim lembut dikukus dengan ayam, jahe, dan kecap asin ala Chinese-Indonesia.',
                    'price' => 28000, 'sort' => 10,
                    'variants' => [
                        ['Porsi Kecil',  'porsi-kecil',  0,    1],
                        ['Porsi Normal', 'porsi-normal', 5000, 2],
                    ],
                ],
                [
                    'name' => 'Nasi Kebuli Kambing', 'slug' => 'nasi-kebuli-kambing',
                    'desc' => 'Nasi kebuli nasi basmati dengan daging kambing dan bumbu rempah Timur Tengah.',
                    'price' => 55000, 'sort' => 11,
                    'variants' => [],
                ],
                [
                    'name' => 'Nasi Tumpeng Mini', 'slug' => 'nasi-tumpeng-mini',
                    'desc' => 'Nasi kuning kerucut mini dengan aneka lauk lengkap, cocok untuk perayaan.',
                    'price' => 65000, 'sort' => 12,
                    'variants' => [],
                ],
                [
                    'name' => 'Nasi Lemak', 'slug' => 'nasi-lemak',
                    'desc' => 'Nasi lemak santan harum dengan rendang kering, sambal, telur, dan kacang goreng.',
                    'price' => 30000, 'sort' => 13,
                    'variants' => [],
                ],
                [
                    'name' => 'Nasi Pecel', 'slug' => 'nasi-pecel',
                    'desc' => 'Nasi dengan aneka sayuran rebus disiram bumbu kacang pedas manis khas Madiun.',
                    'price' => 22000, 'sort' => 14,
                    'variants' => [
                        ['Bumbu Biasa', 'bumbu-biasa', 0,    1],
                        ['Bumbu Pedas', 'bumbu-pedas', 2000, 2],
                    ],
                ],
                [
                    'name' => 'Nasi Rawon', 'slug' => 'nasi-rawon',
                    'desc' => 'Nasi dengan rawon daging sapi berkuah hitam keluak khas Surabaya.',
                    'price' => 38000, 'sort' => 15,
                    'variants' => [
                        ['Kuah Biasa', 'kuah-biasa', 0,    1],
                        ['Kuah Pedas', 'kuah-pedas', 2000, 2],
                    ],
                ],
            ],

            // ════════════════════════════════════════════════════
            // 2. LAUK PAUK  (20 item)
            // ════════════════════════════════════════════════════
            'lauk-pauk' => [
                [
                    'name' => 'Rendang Daging Sapi', 'slug' => 'rendang-daging-sapi',
                    'desc' => 'Rendang daging sapi dimasak lama dengan santan dan 40 jenis rempah khas Minangkabau.',
                    'price' => 45000, 'sort' => 1,
                    'variants' => [
                        ['Porsi Normal',    'porsi-normal',    0,     1],
                        ['Porsi Jumbo',     'porsi-jumbo',     15000, 2],
                        ['Pedas',           'pedas',           2000,  3],
                        ['Extra Pedas',     'extra-pedas',     4000,  4],
                    ],
                ],
                [
                    'name' => 'Ayam Goreng Kremes', 'slug' => 'ayam-goreng-kremes',
                    'desc' => 'Ayam goreng renyah dibalut kremes tepung berbumbu gurih keemasan.',
                    'price' => 30000, 'sort' => 2,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     2000, 2],
                    ],
                ],
                [
                    'name' => 'Ayam Goreng Kunyit', 'slug' => 'ayam-goreng-kunyit',
                    'desc' => 'Ayam dimarinasi kunyit, ketumbar, dan serai, digoreng hingga kekuningan harum.',
                    'price' => 28000, 'sort' => 3,
                    'variants' => [],
                ],
                [
                    'name' => 'Ayam Bakar Kecap', 'slug' => 'ayam-bakar-kecap',
                    'desc' => 'Ayam dimarinasi kecap manis, bawang putih, kemiri, dipanggang bara arang.',
                    'price' => 32000, 'sort' => 4,
                    'variants' => [
                        ['Original',    'original',    0,    1],
                        ['Pedas Manis', 'pedas-manis', 2000, 2],
                    ],
                ],
                [
                    'name' => 'Ayam Geprek', 'slug' => 'ayam-geprek',
                    'desc' => 'Ayam goreng crispy dipukul pipih dan dicampur sambal bawang sesuai level kepedasan.',
                    'price' => 28000, 'sort' => 5,
                    'variants' => [
                        ['Level 1 (Ringan)',      'level-1', 0,    1],
                        ['Level 2 (Sedang)',      'level-2', 1000, 2],
                        ['Level 3 (Pedas)',       'level-3', 2000, 3],
                        ['Level 4 (Extra Pedas)', 'level-4', 3000, 4],
                    ],
                ],
                [
                    'name' => 'Ayam Pop', 'slug' => 'ayam-pop',
                    'desc' => 'Ayam direbus air kelapa hingga putih pucat, khas Padang, disajikan sambal hijau.',
                    'price' => 30000, 'sort' => 6,
                    'variants' => [],
                ],
                [
                    'name' => 'Ikan Bakar Bumbu Kuning', 'slug' => 'ikan-bakar-bumbu-kuning',
                    'desc' => 'Ikan kakap dimarinasi bumbu kuning kunyit, serai, jeruk nipis, dipanggang arang.',
                    'price' => 42000, 'sort' => 7,
                    'variants' => [
                        ['Bumbu Biasa', 'bumbu-biasa', 0,    1],
                        ['Bumbu Pedas', 'bumbu-pedas', 3000, 2],
                    ],
                ],
                [
                    'name' => 'Ikan Gurame Asam Manis', 'slug' => 'ikan-gurame-asam-manis',
                    'desc' => 'Gurame goreng krispi disiram saus asam manis dengan paprika dan nanas muda.',
                    'price' => 55000, 'sort' => 8,
                    'variants' => [],
                ],
                [
                    'name' => 'Ikan Tongkol Bumbu Hitam', 'slug' => 'ikan-tongkol-bumbu-hitam',
                    'desc' => 'Ikan tongkol dimasak bumbu hitam khas Manado dengan rempah dan kluwak.',
                    'price' => 30000, 'sort' => 9,
                    'variants' => [],
                ],
                [
                    'name' => 'Ikan Lele Goreng', 'slug' => 'ikan-lele-goreng',
                    'desc' => 'Lele goreng kering renyah disajikan dengan sambal bawang dan lalapan segar.',
                    'price' => 22000, 'sort' => 10,
                    'variants' => [
                        ['Sambal Original', 'sambal-original', 0,    1],
                        ['Sambal Pedas',    'sambal-pedas',    2000, 2],
                    ],
                ],
                [
                    'name' => 'Udang Saus Padang', 'slug' => 'udang-saus-padang',
                    'desc' => 'Udang jumbo dimasak saus Padang merah pedas dengan paprika dan bawang bombai.',
                    'price' => 48000, 'sort' => 11,
                    'variants' => [
                        ['Original',    'original',    0,    1],
                        ['Extra Pedas', 'extra-pedas', 3000, 2],
                    ],
                ],
                [
                    'name' => 'Cumi Bumbu Hitam', 'slug' => 'cumi-bumbu-hitam',
                    'desc' => 'Cumi dimasak dengan tinta cumi dan rempah hitam, kaya rasa gurih laut.',
                    'price' => 45000, 'sort' => 12,
                    'variants' => [],
                ],
                [
                    'name' => 'Tempe Orek Kering', 'slug' => 'tempe-orek-kering',
                    'desc' => 'Tempe goreng kering dimasak kecap dengan teri medan, cabai merah, dan bawang.',
                    'price' => 18000, 'sort' => 13,
                    'variants' => [
                        ['Manis',       'manis',       0,    1],
                        ['Pedas Manis', 'pedas-manis', 2000, 2],
                    ],
                ],
                [
                    'name' => 'Tahu Bumbu Bali', 'slug' => 'tahu-bumbu-bali',
                    'desc' => 'Tahu goreng disiram bumbu Bali merah dengan serai, lengkuas, dan daun jeruk.',
                    'price' => 18000, 'sort' => 14,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     2000, 2],
                    ],
                ],
                [
                    'name' => 'Empal Gepuk', 'slug' => 'empal-gepuk',
                    'desc' => 'Daging sapi dipukul, dimarinasi bumbu manis kuning, digoreng hingga kering harum.',
                    'price' => 38000, 'sort' => 15,
                    'variants' => [],
                ],
                [
                    'name' => 'Dendeng Balado', 'slug' => 'dendeng-balado',
                    'desc' => 'Dendeng sapi iris tipis kering dibalur sambal balado merah khas Minangkabau.',
                    'price' => 42000, 'sort' => 16,
                    'variants' => [
                        ['Original',    'original',    0,    1],
                        ['Pedas',       'pedas',       2000, 2],
                        ['Extra Pedas', 'extra-pedas', 4000, 3],
                    ],
                ],
                [
                    'name' => 'Bebek Goreng Kremes', 'slug' => 'bebek-goreng-kremes',
                    'desc' => 'Bebek muda empuk berbumbu rempah digoreng dengan kremes renyah gurih.',
                    'price' => 45000, 'sort' => 17,
                    'variants' => [],
                ],
                [
                    'name' => 'Bebek Betutu', 'slug' => 'bebek-betutu',
                    'desc' => 'Bebek dibumbui base genep khas Bali, dibungkus pelepah pisang, dipanggang perlahan.',
                    'price' => 55000, 'sort' => 18,
                    'variants' => [],
                ],
                [
                    'name' => 'Paru Goreng Balado', 'slug' => 'paru-goreng-balado',
                    'desc' => 'Paru sapi goreng kering dibalur sambal balado merah pedas, gurih renyah.',
                    'price' => 28000, 'sort' => 19,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     2000, 2],
                    ],
                ],
                [
                    'name' => 'Gulai Kambing', 'slug' => 'gulai-kambing',
                    'desc' => 'Daging kambing empuk dalam kuah gulai kuning kaya rempah dengan santan segar.',
                    'price' => 48000, 'sort' => 20,
                    'variants' => [
                        ['Porsi Kecil',  'porsi-kecil',  0,     1],
                        ['Porsi Normal', 'porsi-normal', 10000, 2],
                        ['Porsi Besar',  'porsi-besar',  20000, 3],
                    ],
                ],
            ],

            // ════════════════════════════════════════════════════
            // 3. SOTO & SUP  (12 item)
            // ════════════════════════════════════════════════════
            'soto-sup' => [
                [
                    'name' => 'Soto Ayam Lamongan', 'slug' => 'soto-ayam-lamongan',
                    'desc' => 'Soto ayam bening khas Lamongan dengan soun, telur, koya gurih, dan sambal.',
                    'price' => 22000, 'sort' => 1,
                    'variants' => [
                        ['Mangkok Biasa', 'mangkok-biasa', 0,    1],
                        ['Mangkok Besar', 'mangkok-besar', 8000, 2],
                    ],
                ],
                [
                    'name' => 'Soto Betawi', 'slug' => 'soto-betawi',
                    'desc' => 'Soto daging sapi berkuah santan susu khas Betawi dengan tomat dan kentang.',
                    'price' => 30000, 'sort' => 2,
                    'variants' => [
                        ['Mangkok Biasa', 'mangkok-biasa', 0,    1],
                        ['Mangkok Besar', 'mangkok-besar', 8000, 2],
                    ],
                ],
                [
                    'name' => 'Soto Kudus', 'slug' => 'soto-kudus',
                    'desc' => 'Soto bening khas Kudus dengan daging kerbau/sapi, taoge, dan kuah jernih rempah.',
                    'price' => 22000, 'sort' => 3,
                    'variants' => [],
                ],
                [
                    'name' => 'Soto Mie Bogor', 'slug' => 'soto-mie-bogor',
                    'desc' => 'Soto mie khas Bogor dengan kikil, risoles, mie kuning, dan kuah rempah segar.',
                    'price' => 28000, 'sort' => 4,
                    'variants' => [],
                ],
                [
                    'name' => 'Rawon Surabaya', 'slug' => 'rawon-surabaya',
                    'desc' => 'Rawon daging sapi berkuah hitam keluak khas Surabaya dengan taoge dan telur asin.',
                    'price' => 35000, 'sort' => 5,
                    'variants' => [
                        ['Mangkok Biasa', 'mangkok-biasa', 0,    1],
                        ['Mangkok Besar', 'mangkok-besar', 8000, 2],
                    ],
                ],
                [
                    'name' => 'Sup Iga Sapi', 'slug' => 'sup-iga-sapi',
                    'desc' => 'Iga sapi empuk dalam kaldu bening dengan wortel, kentang, dan rempah harum.',
                    'price' => 55000, 'sort' => 6,
                    'variants' => [
                        ['Porsi Biasa', 'porsi-biasa', 0,     1],
                        ['Porsi Besar', 'porsi-besar', 15000, 2],
                    ],
                ],
                [
                    'name' => 'Sup Buntut Sapi', 'slug' => 'sup-buntut-sapi',
                    'desc' => 'Sup buntut premium dengan kaldu jernih, wortel, dan kentang — empuk butuh waktu lama.',
                    'price' => 68000, 'sort' => 7,
                    'variants' => [],
                ],
                [
                    'name' => 'Gulai Ayam', 'slug' => 'gulai-ayam',
                    'desc' => 'Ayam dalam kuah gulai kuning kental bersantan kaya rempah cengkeh dan kapulaga.',
                    'price' => 30000, 'sort' => 8,
                    'variants' => [
                        ['Mangkok Biasa', 'mangkok-biasa', 0,    1],
                        ['Mangkok Besar', 'mangkok-besar', 8000, 2],
                    ],
                ],
                [
                    'name' => 'Lodeh Sayuran', 'slug' => 'lodeh-sayuran',
                    'desc' => 'Sayuran muda dalam santan encer berbumbu laos, serai, dan daun salam.',
                    'price' => 22000, 'sort' => 9,
                    'variants' => [],
                ],
                [
                    'name' => 'Opor Ayam', 'slug' => 'opor-ayam',
                    'desc' => 'Ayam dalam kuah opor putih santan gurih dengan kemiri, ketumbar, dan kunyit.',
                    'price' => 30000, 'sort' => 10,
                    'variants' => [
                        ['Porsi Biasa', 'porsi-biasa', 0,     1],
                        ['Porsi Besar', 'porsi-besar', 10000, 2],
                    ],
                ],
                [
                    'name' => 'Pindang Tulang', 'slug' => 'pindang-tulang',
                    'desc' => 'Tulang sapi dalam kuah pindang merah asam segar khas Sumatera Selatan.',
                    'price' => 35000, 'sort' => 11,
                    'variants' => [],
                ],
                [
                    'name' => 'Tongseng Kambing', 'slug' => 'tongseng-kambing',
                    'desc' => 'Kambing dalam kuah tongseng kecap manis dengan kubis, tomat, dan cabai.',
                    'price' => 38000, 'sort' => 12,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     3000, 2],
                    ],
                ],
            ],

            // ════════════════════════════════════════════════════
            // 4. MIE & BIHUN  (12 item)
            // ════════════════════════════════════════════════════
            'mie-bihun' => [
                [
                    'name' => 'Mie Goreng Jawa', 'slug' => 'mie-goreng-jawa',
                    'desc' => 'Mie goreng bumbu Jawa dengan kecap manis, bakso, sawi, dan telur ceplok.',
                    'price' => 22000, 'sort' => 1,
                    'variants' => [
                        ['Original',    'original',    0,    1],
                        ['Pedas',       'pedas',       2000, 2],
                        ['Extra Pedas', 'extra-pedas', 4000, 3],
                    ],
                ],
                [
                    'name' => 'Mie Goreng Seafood', 'slug' => 'mie-goreng-seafood',
                    'desc' => 'Mie goreng dengan udang, cumi, bakso ikan, dan sayuran dalam bumbu gurih.',
                    'price' => 32000, 'sort' => 2,
                    'variants' => [
                        ['Original',    'original',    0,    1],
                        ['Pedas',       'pedas',       2000, 2],
                        ['Extra Pedas', 'extra-pedas', 4000, 3],
                    ],
                ],
                [
                    'name' => 'Mie Rebus Medan', 'slug' => 'mie-rebus-medan',
                    'desc' => 'Mie kuning dalam kuah kental ubi jalar dengan udang, tahu, dan tauge khas Medan.',
                    'price' => 25000, 'sort' => 3,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     2000, 2],
                    ],
                ],
                [
                    'name' => 'Mie Kocok Bandung', 'slug' => 'mie-kocok-bandung',
                    'desc' => 'Mie kuning dalam kaldu sapi bening khas Bandung dengan kikil, taoge, dan seledri.',
                    'price' => 28000, 'sort' => 4,
                    'variants' => [],
                ],
                [
                    'name' => 'Mie Ayam Bakso', 'slug' => 'mie-ayam-bakso',
                    'desc' => 'Mie ayam topping daging ayam cincang bawang putih dengan bakso dan kuah kaldu.',
                    'price' => 25000, 'sort' => 5,
                    'variants' => [
                        ['Porsi Kecil',  'porsi-kecil',  0,    1],
                        ['Porsi Normal', 'porsi-normal', 5000, 2],
                    ],
                ],
                [
                    'name' => 'Bihun Goreng', 'slug' => 'bihun-goreng',
                    'desc' => 'Bihun digoreng dengan bumbu kecap, ayam suwir, sayuran, dan telur orak-arik.',
                    'price' => 20000, 'sort' => 6,
                    'variants' => [
                        ['Original',    'original',    0,    1],
                        ['Pedas',       'pedas',       2000, 2],
                        ['Extra Pedas', 'extra-pedas', 4000, 3],
                    ],
                ],
                [
                    'name' => 'Kwetiau Goreng', 'slug' => 'kwetiau-goreng',
                    'desc' => 'Kwetiau lebar digoreng dengan kecap hitam, daging sapi, tauge, dan sayuran.',
                    'price' => 28000, 'sort' => 7,
                    'variants' => [
                        ['Original',    'original',    0,    1],
                        ['Pedas',       'pedas',       2000, 2],
                        ['Extra Pedas', 'extra-pedas', 4000, 3],
                    ],
                ],
                [
                    'name' => 'Bakmi Goreng Jawa', 'slug' => 'bakmi-goreng-jawa',
                    'desc' => 'Bakmi tipis khas Jawa digoreng dengan bumbu wijen, bakso, dan sayuran segar.',
                    'price' => 22000, 'sort' => 8,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     2000, 2],
                    ],
                ],
                [
                    'name' => 'Spaghetti Goreng Pedas', 'slug' => 'spaghetti-goreng-pedas',
                    'desc' => 'Spaghetti digoreng ala Indonesian dengan kecap, cabai, dan ayam suwir pedas.',
                    'price' => 28000, 'sort' => 9,
                    'variants' => [],
                ],
                [
                    'name' => 'Laksa Betawi', 'slug' => 'laksa-betawi',
                    'desc' => 'Mie dalam kuah santan kunyit khas Betawi dengan serundeng, daun kemangi, dan udang.',
                    'price' => 28000, 'sort' => 10,
                    'variants' => [],
                ],
                [
                    'name' => 'Mie Cakalang', 'slug' => 'mie-cakalang',
                    'desc' => 'Mie goreng dengan ikan cakalang fufu asap khas Manado, pedas gurih berbumbu.',
                    'price' => 32000, 'sort' => 11,
                    'variants' => [],
                ],
                [
                    'name' => 'Bihun Kuah Ayam', 'slug' => 'bihun-kuah-ayam',
                    'desc' => 'Bihun dalam kaldu ayam bening hangat dengan sawi, tomat, dan bawang goreng.',
                    'price' => 20000, 'sort' => 12,
                    'variants' => [],
                ],
            ],

            // ════════════════════════════════════════════════════
            // 5. SATE & BAKAR  (12 item)
            // ════════════════════════════════════════════════════
            'sate-bakar' => [
                [
                    'name' => 'Sate Ayam Madura', 'slug' => 'sate-ayam-madura',
                    'desc' => 'Sate ayam bumbu kacang manis khas Madura dengan lontong dan irisan bawang merah.',
                    'price' => 25000, 'sort' => 1,
                    'variants' => [
                        ['10 Tusuk', '10-tusuk', 0,     1],
                        ['20 Tusuk', '20-tusuk', 22000, 2],
                    ],
                ],
                [
                    'name' => 'Sate Kambing', 'slug' => 'sate-kambing',
                    'desc' => 'Sate kambing muda empuk dipanggang arang, disajikan dengan kecap irisan tomat bawang.',
                    'price' => 35000, 'sort' => 2,
                    'variants' => [
                        ['10 Tusuk', '10-tusuk', 0,     1],
                        ['20 Tusuk', '20-tusuk', 30000, 2],
                    ],
                ],
                [
                    'name' => 'Sate Sapi', 'slug' => 'sate-sapi',
                    'desc' => 'Sate daging sapi pilihan dengan bumbu kacang atau bumbu merah, dipanggang arang.',
                    'price' => 30000, 'sort' => 3,
                    'variants' => [
                        ['Bumbu Kacang', 'bumbu-kacang', 0,    1],
                        ['Bumbu Merah',  'bumbu-merah',  2000, 2],
                    ],
                ],
                [
                    'name' => 'Sate Padang', 'slug' => 'sate-padang',
                    'desc' => 'Sate daging sapi/lidah dengan kuah kuning kental rempah khas Padang Panjang.',
                    'price' => 32000, 'sort' => 4,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     3000, 2],
                    ],
                ],
                [
                    'name' => 'Sate Lilit Bali', 'slug' => 'sate-lilit-bali',
                    'desc' => 'Daging ikan/babi cincang bumbu base genep dililitkan bambu, dipanggang wangi.',
                    'price' => 30000, 'sort' => 5,
                    'variants' => [],
                ],
                [
                    'name' => 'Sate Maranggi', 'slug' => 'sate-maranggi',
                    'desc' => 'Sate daging sapi berbumbu ketumbar kunyit khas Purwakarta, manis dan harum.',
                    'price' => 32000, 'sort' => 6,
                    'variants' => [],
                ],
                [
                    'name' => 'Ikan Bakar Jimbaran', 'slug' => 'ikan-bakar-jimbaran',
                    'desc' => 'Ikan bakar ala Jimbaran Bali dengan bumbu kuning segar dan sambal matah.',
                    'price' => 55000, 'sort' => 7,
                    'variants' => [
                        ['Bumbu Original', 'bumbu-original', 0,    1],
                        ['Bumbu Pedas',    'bumbu-pedas',    3000, 2],
                    ],
                ],
                [
                    'name' => 'Ayam Bakar Taliwang', 'slug' => 'ayam-bakar-taliwang',
                    'desc' => 'Ayam kampung berbumbu Taliwang super pedas khas Lombok, dibakar arang panas.',
                    'price' => 40000, 'sort' => 8,
                    'variants' => [
                        ['Original',    'original',    0,    1],
                        ['Extra Pedas', 'extra-pedas', 5000, 2],
                    ],
                ],
                [
                    'name' => 'Udang Bakar', 'slug' => 'udang-bakar',
                    'desc' => 'Udang jumbo dimarinasi bumbu kuning bawang putih jeruk limau, dipanggang bara.',
                    'price' => 50000, 'sort' => 9,
                    'variants' => [],
                ],
                [
                    'name' => 'Cumi Bakar', 'slug' => 'cumi-bakar',
                    'desc' => 'Cumi diisi bumbu pedas manis lalu dipanggang hingga harum sedikit gosong.',
                    'price' => 45000, 'sort' => 10,
                    'variants' => [
                        ['Original',    'original',    0,    1],
                        ['Pedas Manis', 'pedas-manis', 3000, 2],
                    ],
                ],
                [
                    'name' => 'Bakso Bakar', 'slug' => 'bakso-bakar',
                    'desc' => 'Bakso sapi dibakar arang disiram bumbu kecap cabai, renyah di luar lembut dalam.',
                    'price' => 18000, 'sort' => 11,
                    'variants' => [
                        ['5 Biji',  '5-biji',  0,     1],
                        ['10 Biji', '10-biji', 15000, 2],
                    ],
                ],
                [
                    'name' => 'Jagung Bakar', 'slug' => 'jagung-bakar',
                    'desc' => 'Jagung manis segar dibakar arang dengan oles mentega atau bumbu pedas manis.',
                    'price' => 12000, 'sort' => 12,
                    'variants' => [
                        ['Mentega Asin',  'mentega-asin',  0,    1],
                        ['Pedas Manis',   'pedas-manis',   1000, 2],
                        ['Keju Susu',     'keju-susu',     2000, 3],
                    ],
                ],
            ],

            // ════════════════════════════════════════════════════
            // 6. PECEL & SALAD TRADISIONAL  (8 item)
            // ════════════════════════════════════════════════════
            'pecel-salad' => [
                [
                    'name' => 'Pecel Madiun', 'slug' => 'pecel-madiun',
                    'desc' => 'Aneka sayuran rebus disiram bumbu kacang pedas manis khas Madiun dengan peyek.',
                    'price' => 18000, 'sort' => 1,
                    'variants' => [
                        ['Bumbu Biasa', 'bumbu-biasa', 0,    1],
                        ['Bumbu Pedas', 'bumbu-pedas', 2000, 2],
                    ],
                ],
                [
                    'name' => 'Gado-gado Jakarta', 'slug' => 'gado-gado-jakarta',
                    'desc' => 'Sayuran rebus, tahu, tempe, lontong disiram saus kacang kental khas Betawi.',
                    'price' => 22000, 'sort' => 2,
                    'variants' => [
                        ['Bumbu Biasa', 'bumbu-biasa', 0,    1],
                        ['Bumbu Pedas', 'bumbu-pedas', 2000, 2],
                    ],
                ],
                [
                    'name' => 'Karedok Sunda', 'slug' => 'karedok-sunda',
                    'desc' => 'Sayuran mentah segar disiram bumbu kacang terasi khas Sunda.',
                    'price' => 20000, 'sort' => 3,
                    'variants' => [
                        ['Bumbu Biasa', 'bumbu-biasa', 0,    1],
                        ['Bumbu Pedas', 'bumbu-pedas', 2000, 2],
                    ],
                ],
                [
                    'name' => 'Urap Sayuran', 'slug' => 'urap-sayuran',
                    'desc' => 'Sayuran rebus diaduk kelapa parut berbumbu bawang putih, cabai, dan kencur.',
                    'price' => 18000, 'sort' => 4,
                    'variants' => [],
                ],
                [
                    'name' => 'Lotek Bandung', 'slug' => 'lotek-bandung',
                    'desc' => 'Mirip gado-gado khas Bandung dengan lontong dan bumbu kacang kencur.',
                    'price' => 20000, 'sort' => 5,
                    'variants' => [],
                ],
                [
                    'name' => 'Rujak Cingur', 'slug' => 'rujak-cingur',
                    'desc' => 'Rujak khas Surabaya dengan irisan cingur (hidung sapi) dan saus petis hitam.',
                    'price' => 28000, 'sort' => 6,
                    'variants' => [],
                ],
                [
                    'name' => 'Asinan Betawi', 'slug' => 'asinan-betawi',
                    'desc' => 'Asinan sayur dan buah khas Betawi dalam kuah cuka pedas manis dengan kerupuk mie.',
                    'price' => 18000, 'sort' => 7,
                    'variants' => [],
                ],
                [
                    'name' => 'Ketoprak', 'slug' => 'ketoprak',
                    'desc' => 'Ketupat, bihun, tahu, taoge dengan saus kacang kecap jeruk limau khas Jakarta.',
                    'price' => 18000, 'sort' => 8,
                    'variants' => [],
                ],
            ],

            // ════════════════════════════════════════════════════
            // 7. CAMILAN & GORENGAN  (15 item)
            // ════════════════════════════════════════════════════
            'camilan-gorengan' => [
                [
                    'name' => 'Bakwan Sayuran', 'slug' => 'bakwan-sayuran',
                    'desc' => 'Gorengan tepung berbumbu isi wortel, jagung, kol, dan daun bawang.',
                    'price' => 8000, 'sort' => 1,
                    'variants' => [
                        ['3 Biji', '3-biji', 0,    1],
                        ['5 Biji', '5-biji', 5000, 2],
                    ],
                ],
                [
                    'name' => 'Tahu Goreng Crispy', 'slug' => 'tahu-goreng-crispy',
                    'desc' => 'Tahu digoreng kering crispy berlapis tepung ringan, disajikan sambal kecap.',
                    'price' => 10000, 'sort' => 2,
                    'variants' => [
                        ['5 Biji',  '5-biji',  0,     1],
                        ['10 Biji', '10-biji', 8000, 2],
                    ],
                ],
                [
                    'name' => 'Tempe Goreng Crispy', 'slug' => 'tempe-goreng-crispy',
                    'desc' => 'Tempe iris tipis digoreng kering sangat renyah dengan bumbu bawang putih.',
                    'price' => 10000, 'sort' => 3,
                    'variants' => [
                        ['5 Biji',  '5-biji',  0,    1],
                        ['10 Biji', '10-biji', 8000, 2],
                    ],
                ],
                [
                    'name' => 'Perkedel Kentang', 'slug' => 'perkedel-kentang',
                    'desc' => 'Perkedel kentang halus berbumbu dengan irisan daun bawang, digoreng emas.',
                    'price' => 8000, 'sort' => 4,
                    'variants' => [
                        ['3 Biji', '3-biji', 0,    1],
                        ['5 Biji', '5-biji', 5000, 2],
                    ],
                ],
                [
                    'name' => 'Martabak Telur', 'slug' => 'martabak-telur',
                    'desc' => 'Martabak tipis isi telur ayam, daging cincang, daun bawang, dan bawang merah.',
                    'price' => 22000, 'sort' => 5,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     2000, 2],
                    ],
                ],
                [
                    'name' => 'Martabak Manis', 'slug' => 'martabak-manis',
                    'desc' => 'Martabak tebal lembut dan spongy dengan pilihan topping manis favorit.',
                    'price' => 28000, 'sort' => 6,
                    'variants' => [
                        ['Cokelat Keju',    'cokelat-keju',    3000, 1],
                        ['Kacang Meses',    'kacang-meses',    0,    2],
                        ['Pisang Cokelat',  'pisang-cokelat',  2000, 3],
                    ],
                ],
                [
                    'name' => 'Pisang Goreng Crispy', 'slug' => 'pisang-goreng-crispy',
                    'desc' => 'Pisang kepok digoreng dengan tepung crispy ringan, disajikan hangat.',
                    'price' => 12000, 'sort' => 7,
                    'variants' => [
                        ['5 Biji',  '5-biji',  0,     1],
                        ['10 Biji', '10-biji', 10000, 2],
                    ],
                ],
                [
                    'name' => 'Ubi Goreng Renyah', 'slug' => 'ubi-goreng-renyah',
                    'desc' => 'Ubi jalar manis digoreng kering dengan balutan bumbu pedas manis.',
                    'price' => 12000, 'sort' => 8,
                    'variants' => [
                        ['Porsi Kecil', 'porsi-kecil', 0,    1],
                        ['Porsi Besar', 'porsi-besar', 6000, 2],
                    ],
                ],
                [
                    'name' => 'Singkong Goreng', 'slug' => 'singkong-goreng',
                    'desc' => 'Singkong rebus lalu digoreng kering, renyah di luar dan lembut di dalam.',
                    'price' => 10000, 'sort' => 9,
                    'variants' => [
                        ['Porsi Kecil', 'porsi-kecil', 0,    1],
                        ['Porsi Besar', 'porsi-besar', 5000, 2],
                    ],
                ],
                [
                    'name' => 'Risoles Ragout', 'slug' => 'risoles-ragout',
                    'desc' => 'Risoles kulit tipis isi ragout ayam atau sayuran, digoreng keemasan.',
                    'price' => 8000, 'sort' => 10,
                    'variants' => [
                        ['Isi Ragout Ayam',   'isi-ragout-ayam',    0,    1],
                        ['Isi Sayuran',       'isi-sayuran',        0,    2],
                    ],
                ],
                [
                    'name' => 'Pastel Goreng', 'slug' => 'pastel-goreng',
                    'desc' => 'Pastel renyah isi wortel, bihun, telur rebus, dan daun bawang berbumbu.',
                    'price' => 7000, 'sort' => 11,
                    'variants' => [],
                ],
                [
                    'name' => 'Kroket Kentang', 'slug' => 'kroket-kentang',
                    'desc' => 'Kroket bulat isi kentang halus ragout ayam berlapis tepung panir digoreng.',
                    'price' => 10000, 'sort' => 12,
                    'variants' => [
                        ['3 Biji', '3-biji', 0,    1],
                        ['5 Biji', '5-biji', 8000, 2],
                    ],
                ],
                [
                    'name' => 'Empek-empek Palembang', 'slug' => 'empek-empek-palembang',
                    'desc' => 'Empek-empek kapal selam isi telur dalam kuah cuko asam manis pedas Palembang.',
                    'price' => 20000, 'sort' => 13,
                    'variants' => [
                        ['Kuah Original', 'kuah-original', 0,    1],
                        ['Kuah Pedas',    'kuah-pedas',    2000, 2],
                    ],
                ],
                [
                    'name' => 'Kerupuk Udang', 'slug' => 'kerupuk-udang',
                    'desc' => 'Kerupuk udang asli Sidoarjo, renyah ringan dengan rasa udang alami gurih.',
                    'price' => 8000, 'sort' => 14,
                    'variants' => [
                        ['Porsi Kecil', 'porsi-kecil', 0,    1],
                        ['Porsi Besar', 'porsi-besar', 6000, 2],
                    ],
                ],
                [
                    'name' => 'Cireng Bumbu Rujak', 'slug' => 'cireng-bumbu-rujak',
                    'desc' => 'Aci goreng kenyal renyah disajikan dengan bumbu rujak asam pedas manis.',
                    'price' => 10000, 'sort' => 15,
                    'variants' => [
                        ['5 Biji',  '5-biji',  0,    1],
                        ['10 Biji', '10-biji', 8000, 2],
                    ],
                ],
            ],

            // ════════════════════════════════════════════════════
            // 8. SAYURAN TUMIS & BERKUAH  (10 item)
            // ════════════════════════════════════════════════════
            'sayuran-tumis' => [
                [
                    'name' => 'Tumis Kangkung Belacan', 'slug' => 'tumis-kangkung-belacan',
                    'desc' => 'Kangkung ditumis dengan terasi belacan, bawang merah, cabai, dan sedikit kecap.',
                    'price' => 18000, 'sort' => 1,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     2000, 2],
                    ],
                ],
                [
                    'name' => 'Tumis Buncis Tempe', 'slug' => 'tumis-buncis-tempe',
                    'desc' => 'Buncis dan tempe ditumis bumbu bawang putih kecap manis cabai hijau.',
                    'price' => 18000, 'sort' => 2,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     2000, 2],
                    ],
                ],
                [
                    'name' => 'Capcay Kuah', 'slug' => 'capcay-kuah',
                    'desc' => 'Aneka sayuran segar dalam kuah kental bumbu tiram, bawang putih, dan jahe.',
                    'price' => 22000, 'sort' => 3,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     2000, 2],
                    ],
                ],
                [
                    'name' => 'Capcay Goreng', 'slug' => 'capcay-goreng',
                    'desc' => 'Aneka sayuran ditumis kering dengan bumbu bawang putih dan saus tiram.',
                    'price' => 22000, 'sort' => 4,
                    'variants' => [],
                ],
                [
                    'name' => 'Sayur Lodeh', 'slug' => 'sayur-lodeh',
                    'desc' => 'Labu muda, kacang panjang, terong dalam santan encer rempah Jawa.',
                    'price' => 18000, 'sort' => 5,
                    'variants' => [],
                ],
                [
                    'name' => 'Sayur Asem', 'slug' => 'sayur-asem',
                    'desc' => 'Sayuran segar dalam kuah asam segar dengan belimbing wuluh dan asam jawa.',
                    'price' => 18000, 'sort' => 6,
                    'variants' => [],
                ],
                [
                    'name' => 'Tumis Tauge Ikan Asin', 'slug' => 'tumis-tauge-ikan-asin',
                    'desc' => 'Tauge renyah ditumis dengan ikan asin, cabai rawit, dan bawang merah.',
                    'price' => 18000, 'sort' => 7,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     2000, 2],
                    ],
                ],
                [
                    'name' => 'Tumis Kacang Panjang', 'slug' => 'tumis-kacang-panjang',
                    'desc' => 'Kacang panjang ditumis dengan tempe, bawang merah, cabai, dan tomat.',
                    'price' => 16000, 'sort' => 8,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     2000, 2],
                    ],
                ],
                [
                    'name' => 'Lalapan Komplit', 'slug' => 'lalapan-komplit',
                    'desc' => 'Ketimun, kemangi, kol, pete, terong mentah segar dengan dua macam sambal.',
                    'price' => 12000, 'sort' => 9,
                    'variants' => [],
                ],
                [
                    'name' => 'Pepes Jamur Tiram', 'slug' => 'pepes-jamur-tiram',
                    'desc' => 'Jamur tiram segar dibumbui kunyit kemangi lalu dibungkus daun pisang dikukus.',
                    'price' => 20000, 'sort' => 10,
                    'variants' => [
                        ['Original', 'original', 0,    1],
                        ['Pedas',    'pedas',     2000, 2],
                    ],
                ],
            ],

            // ════════════════════════════════════════════════════
            // 9. MINUMAN TRADISIONAL  (12 item)
            // ════════════════════════════════════════════════════
            'minuman-tradisional' => [
                [
                    'name' => 'Es Dawet', 'slug' => 'es-dawet',
                    'desc' => 'Es dawet cendol hijau dengan santan, gula merah cair, dan es serut.',
                    'price' => 10000, 'sort' => 1,
                    'variants' => [
                        ['Gelas Regular', 'gelas-regular', 0,    1],
                        ['Gelas Jumbo',   'gelas-jumbo',   5000, 2],
                    ],
                ],
                [
                    'name' => 'Es Cendol', 'slug' => 'es-cendol',
                    'desc' => 'Es cendol tepung beras pandan hijau dengan kuah santan dan gula aren.',
                    'price' => 10000, 'sort' => 2,
                    'variants' => [
                        ['Gelas Regular', 'gelas-regular', 0,    1],
                        ['Gelas Jumbo',   'gelas-jumbo',   5000, 2],
                    ],
                ],
                [
                    'name' => 'Es Teh Manis', 'slug' => 'es-teh-manis',
                    'desc' => 'Teh hitam dengan gula cair disajikan dingin menyegarkan.',
                    'price' => 6000, 'sort' => 3,
                    'variants' => [
                        ['Manis Penuh',  'manis-penuh',  0,    1],
                        ['Manis Sedang', 'manis-sedang', 0,    2],
                        ['Tawar',        'tawar',        0,    3],
                    ],
                ],
                [
                    'name' => 'Es Jeruk Peras', 'slug' => 'es-jeruk-peras',
                    'desc' => 'Jeruk siam diperas langsung, segar asam manis alami dengan es batu.',
                    'price' => 8000, 'sort' => 4,
                    'variants' => [
                        ['Manis',        'manis',        0,    1],
                        ['Kurang Manis', 'kurang-manis', 0,    2],
                    ],
                ],
                [
                    'name' => 'Wedang Jahe', 'slug' => 'wedang-jahe',
                    'desc' => 'Jahe merah bakar dengan gula batu, serai, dan kayu manis, minum hangat.',
                    'price' => 8000, 'sort' => 5,
                    'variants' => [
                        ['Gelas Regular', 'gelas-regular', 0,    1],
                        ['Gelas Besar',   'gelas-besar',   4000, 2],
                    ],
                ],
                [
                    'name' => 'Wedang Uwuh', 'slug' => 'wedang-uwuh',
                    'desc' => 'Minuman rempah khas Yogyakarta dengan kayu secang merah, kapulaga, dan cengkeh.',
                    'price' => 10000, 'sort' => 6,
                    'variants' => [],
                ],
                [
                    'name' => 'Jamu Kunyit Asam', 'slug' => 'jamu-kunyit-asam',
                    'desc' => 'Jamu tradisional kunyit asam jawa gula aren, menyehatkan dan menyegarkan.',
                    'price' => 8000, 'sort' => 7,
                    'variants' => [],
                ],
                [
                    'name' => 'Bajigur', 'slug' => 'bajigur',
                    'desc' => 'Minuman hangat khas Sunda dari santan, gula aren, jahe, dan kolang-kaling.',
                    'price' => 10000, 'sort' => 8,
                    'variants' => [],
                ],
                [
                    'name' => 'Bandrek', 'slug' => 'bandrek',
                    'desc' => 'Minuman rempah hangat khas Sunda dengan jahe, kayu manis, gula aren, dan susu.',
                    'price' => 10000, 'sort' => 9,
                    'variants' => [],
                ],
                [
                    'name' => 'Es Cincau Hitam', 'slug' => 'es-cincau-hitam',
                    'desc' => 'Cincau hitam kenyal dalam santan manis dengan es batu dan gula aren.',
                    'price' => 8000, 'sort' => 10,
                    'variants' => [
                        ['Gelas Regular', 'gelas-regular', 0,    1],
                        ['Gelas Jumbo',   'gelas-jumbo',   5000, 2],
                    ],
                ],
                [
                    'name' => 'Teh Tarik', 'slug' => 'teh-tarik',
                    'desc' => 'Teh susu kental manis diulang-ulang dari ketinggian hingga berbuih, ala Melayu.',
                    'price' => 10000, 'sort' => 11,
                    'variants' => [
                        ['Hangat', 'hangat', 0,    1],
                        ['Dingin', 'dingin', 1000, 2],
                    ],
                ],
                [
                    'name' => 'Kopi Tubruk', 'slug' => 'kopi-tubruk',
                    'desc' => 'Kopi bubuk kasar langsung diseduh air panas, tradisional dan kuat.',
                    'price' => 8000, 'sort' => 12,
                    'variants' => [
                        ['Manis',        'manis',        0,    1],
                        ['Kurang Manis', 'kurang-manis', 0,    2],
                        ['Pahit',        'pahit',        0,    3],
                    ],
                ],
            ],

            // ════════════════════════════════════════════════════
            // 10. DESSERT TRADISIONAL  (9 item)
            // ════════════════════════════════════════════════════
            'dessert-tradisional' => [
                [
                    'name' => 'Klepon', 'slug' => 'klepon',
                    'desc' => 'Kue bola hijau dari tepung ketan isi gula merah, dilumur kelapa parut kukus.',
                    'price' => 10000, 'sort' => 1,
                    'variants' => [
                        ['5 Biji',  '5-biji',  0,     1],
                        ['10 Biji', '10-biji', 8000, 2],
                    ],
                ],
                [
                    'name' => 'Onde-onde', 'slug' => 'onde-onde',
                    'desc' => 'Bola ketan wijen isi kacang hijau manis, renyah di luar lembut di dalam.',
                    'price' => 10000, 'sort' => 2,
                    'variants' => [
                        ['3 Biji', '3-biji', 0,    1],
                        ['6 Biji', '6-biji', 9000, 2],
                    ],
                ],
                [
                    'name' => 'Kue Putu', 'slug' => 'kue-putu',
                    'desc' => 'Kue kukus bambu dengan tepung beras pandan, isi gula merah, dilumuri kelapa.',
                    'price' => 12000, 'sort' => 3,
                    'variants' => [
                        ['5 Biji',  '5-biji',  0,     1],
                        ['10 Biji', '10-biji', 10000, 2],
                    ],
                ],
                [
                    'name' => 'Lapis Legit', 'slug' => 'lapis-legit',
                    'desc' => 'Kue lapis banyak lapisan tipis dengan rempah spekuk, padat dan mewah.',
                    'price' => 25000, 'sort' => 4,
                    'variants' => [
                        ['Per Slice', 'per-slice', 0,      1],
                        ['Per Loaf',  'per-loaf',  125000, 2],
                    ],
                ],
                [
                    'name' => 'Getuk Lindri', 'slug' => 'getuk-lindri',
                    'desc' => 'Singkong kukus dihaluskan dengan gula kelapa, dibentuk cantik warna-warni.',
                    'price' => 10000, 'sort' => 5,
                    'variants' => [
                        ['Porsi Kecil', 'porsi-kecil', 0,    1],
                        ['Porsi Besar', 'porsi-besar', 8000, 2],
                    ],
                ],
                [
                    'name' => 'Serabi Solo', 'slug' => 'serabi-solo',
                    'desc' => 'Serabi tradisional Solo dari tepung beras bersantan, dimasak di wajan tanah liat.',
                    'price' => 12000, 'sort' => 6,
                    'variants' => [
                        ['2 Lembar', '2-lembar', 0,     1],
                        ['4 Lembar', '4-lembar', 10000, 2],
                    ],
                ],
                [
                    'name' => 'Kolak Pisang', 'slug' => 'kolak-pisang',
                    'desc' => 'Pisang kepok dalam kuah santan gula merah dengan kolang-kaling dan ubi.',
                    'price' => 12000, 'sort' => 7,
                    'variants' => [
                        ['Mangkok Kecil', 'mangkok-kecil', 0,    1],
                        ['Mangkok Besar', 'mangkok-besar', 5000, 2],
                    ],
                ],
                [
                    'name' => 'Es Teler', 'slug' => 'es-teler',
                    'desc' => 'Es campuran alpukat, kelapa muda, nangka, santan, dan sirup cokelat segar.',
                    'price' => 15000, 'sort' => 8,
                    'variants' => [
                        ['Gelas Regular', 'gelas-regular', 0,    1],
                        ['Gelas Jumbo',   'gelas-jumbo',   7000, 2],
                    ],
                ],
                [
                    'name' => 'Bubur Sumsum', 'slug' => 'bubur-sumsum',
                    'desc' => 'Bubur tepung beras putih lembut disiram kinca gula merah dan santan gurih.',
                    'price' => 12000, 'sort' => 9,
                    'variants' => [
                        ['Mangkok Kecil', 'mangkok-kecil', 0,    1],
                        ['Mangkok Besar', 'mangkok-besar', 5000, 2],
                    ],
                ],
            ],
        ];
    }

    // ── USD Price Map ────────────────────────────────────────────

    private static function getUsdPrices(): array
    {
        return [
            // Nasi & Olahan
            'nasi-goreng-kampung'      => 1.75, 'nasi-goreng-spesial'      => 2.25,
            'nasi-goreng-seafood'      => 2.75, 'nasi-goreng-rendang'      => 3.00,
            'nasi-uduk'                => 1.50, 'nasi-kuning-komplit'       => 2.00,
            'nasi-liwet-solo'          => 2.25, 'nasi-bakar-ikan'           => 2.00,
            'nasi-padang'              => 3.25, 'nasi-tim-ayam'             => 2.00,
            'nasi-kebuli-kambing'      => 4.00, 'nasi-tumpeng-mini'         => 4.50,
            'nasi-lemak'               => 2.25, 'nasi-pecel'                => 1.50,
            'nasi-rawon'               => 2.75,
            // Lauk Pauk
            'rendang-daging-sapi'      => 3.25, 'ayam-goreng-kremes'        => 2.25,
            'ayam-goreng-kunyit'       => 2.00, 'ayam-bakar-kecap'          => 2.50,
            'ayam-geprek'              => 2.00, 'ayam-pop'                  => 2.25,
            'ikan-bakar-bumbu-kuning'  => 3.00, 'ikan-gurame-asam-manis'    => 4.00,
            'ikan-tongkol-bumbu-hitam' => 2.25, 'ikan-lele-goreng'          => 1.50,
            'udang-saus-padang'        => 3.50, 'cumi-bumbu-hitam'          => 3.25,
            'tempe-orek-kering'        => 1.25, 'tahu-bumbu-bali'           => 1.25,
            'empal-gepuk'              => 2.75, 'dendeng-balado'            => 3.00,
            'bebek-goreng-kremes'      => 3.25, 'bebek-betutu'              => 4.00,
            'paru-goreng-balado'       => 2.00, 'gulai-kambing'             => 3.50,
            // Soto & Sup
            'soto-ayam-lamongan'       => 1.50, 'soto-betawi'               => 2.25,
            'soto-kudus'               => 1.50, 'soto-mie-bogor'            => 2.00,
            'rawon-surabaya'           => 2.50, 'sup-iga-sapi'              => 4.00,
            'sup-buntut-sapi'          => 5.00, 'gulai-ayam'                => 2.25,
            'lodeh-sayuran'            => 1.50, 'opor-ayam'                 => 2.25,
            'pindang-tulang'           => 2.50, 'tongseng-kambing'          => 2.75,
            // Mie & Bihun
            'mie-goreng-jawa'          => 1.50, 'mie-goreng-seafood'        => 2.25,
            'mie-rebus-medan'          => 1.75, 'mie-kocok-bandung'         => 2.00,
            'mie-ayam-bakso'           => 1.75, 'bihun-goreng'              => 1.25,
            'kwetiau-goreng'           => 2.00, 'bakmi-goreng-jawa'         => 1.50,
            'spaghetti-goreng-pedas'   => 2.00, 'laksa-betawi'              => 2.00,
            'mie-cakalang'             => 2.25, 'bihun-kuah-ayam'           => 1.25,
            // Sate & Bakar
            'sate-ayam-madura'         => 1.75, 'sate-kambing'              => 2.50,
            'sate-sapi'                => 2.25, 'sate-padang'               => 2.25,
            'sate-lilit-bali'          => 2.25, 'sate-maranggi'             => 2.25,
            'ikan-bakar-jimbaran'      => 4.00, 'ayam-bakar-taliwang'       => 2.75,
            'udang-bakar'              => 3.50, 'cumi-bakar'                => 3.25,
            'bakso-bakar'              => 1.25, 'jagung-bakar'              => 0.90,
            // Pecel & Salad
            'pecel-madiun'             => 1.25, 'gado-gado-jakarta'         => 1.50,
            'karedok-sunda'            => 1.50, 'urap-sayuran'              => 1.25,
            'lotek-bandung'            => 1.50, 'rujak-cingur'              => 2.00,
            'asinan-betawi'            => 1.25, 'ketoprak'                  => 1.25,
            // Camilan & Gorengan
            'bakwan-sayuran'           => 0.60, 'tahu-goreng-crispy'        => 0.70,
            'tempe-goreng-crispy'      => 0.70, 'perkedel-kentang'          => 0.60,
            'martabak-telur'           => 1.50, 'martabak-manis'            => 2.00,
            'pisang-goreng-crispy'     => 0.90, 'ubi-goreng-renyah'         => 0.90,
            'singkong-goreng'          => 0.70, 'risoles-ragout'            => 0.60,
            'pastel-goreng'            => 0.50, 'kroket-kentang'            => 0.70,
            'empek-empek-palembang'    => 1.50, 'kerupuk-udang'             => 0.60,
            'cireng-bumbu-rujak'       => 0.70,
            // Sayuran
            'tumis-kangkung-belacan'   => 1.25, 'tumis-buncis-tempe'        => 1.25,
            'capcay-kuah'              => 1.50, 'capcay-goreng'             => 1.50,
            'sayur-lodeh'              => 1.25, 'sayur-asem'                => 1.25,
            'tumis-tauge-ikan-asin'    => 1.25, 'tumis-kacang-panjang'      => 1.25,
            'lalapan-komplit'          => 0.90, 'pepes-jamur-tiram'         => 1.50,
            // Minuman
            'es-dawet'                 => 0.75, 'es-cendol'                 => 0.75,
            'es-teh-manis'             => 0.45, 'es-jeruk-peras'            => 0.60,
            'wedang-jahe'              => 0.60, 'wedang-uwuh'               => 0.75,
            'jamu-kunyit-asam'         => 0.60, 'bajigur'                   => 0.75,
            'bandrek'                  => 0.75, 'es-cincau-hitam'           => 0.60,
            'teh-tarik'                => 0.75, 'kopi-tubruk'               => 0.60,
            // Dessert
            'klepon'                   => 0.75, 'onde-onde'                 => 0.75,
            'kue-putu'                 => 0.90, 'lapis-legit'               => 1.75,
            'getuk-lindri'             => 0.75, 'serabi-solo'               => 0.90,
            'kolak-pisang'             => 0.90, 'es-teler'                  => 1.10,
            'bubur-sumsum'             => 0.90,
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
                            ':delta'   => (float)$idrDelta,
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
