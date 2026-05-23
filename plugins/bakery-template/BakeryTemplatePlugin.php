<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class BakeryTemplatePlugin implements PluginInterface
{
    public function getName(): string    { return 'Bakery Template'; }
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
                'url'   => '/dashboard/super/bakery-template.php',
                'icon'  => '🍞',
                'label' => 'Bakery Template',
            ];
        }

        return $items;
    }

    // ── Seed Data ────────────────────────────────────────────────

    /**
     * Returns category definitions.
     * Each entry: [name, slug, description, sort_order]
     */
    public static function getCategories(): array
    {
        return [
            ['Roti',              'roti',              'Aneka roti segar pilihan',                    1],
            ['Pastry & Croissant','pastry-croissant',  'Pastry dan croissant renyah berkualitas',     2],
            ['Kue & Cake',        'kue-cake',          'Kue dan cake spesial untuk setiap momen',     3],
            ['Cookies & Snack',   'cookies-snack',     'Cookies dan camilan manis gurih',             4],
            ['Minuman',           'minuman',           'Minuman segar dan hangat',                    5],
            ['Sandwich & Savory', 'sandwich-savory',   'Sandwich dan makanan gurih pilihan',          6],
        ];
    }

    /**
     * Returns menu items keyed by category slug.
     * Each item: [name, slug, description, price, sort_order, variants[]]
     * Each variant: [label, slug, price_delta, sort_order]
     */
    public static function getMenuItems(): array
    {
        return [
            'roti' => [
                [
                    'name' => 'Roti Tawar Putih', 'slug' => 'roti-tawar-putih',
                    'desc' => 'Roti tawar putih lembut untuk sarapan sehari-hari.',
                    'price' => 18000, 'sort' => 1,
                    'variants' => [
                        ['Small (6 lembar)', 'small-6-lembar', 0,     1],
                        ['Besar (12 lembar)', 'besar-12-lembar', 12000, 2],
                    ],
                ],
                [
                    'name' => 'Roti Tawar Gandum', 'slug' => 'roti-tawar-gandum',
                    'desc' => 'Roti tawar whole wheat kaya serat, pilihan sehat.',
                    'price' => 22000, 'sort' => 2,
                    'variants' => [
                        ['Small (6 lembar)', 'small-6-lembar', 0,     1],
                        ['Besar (12 lembar)', 'besar-12-lembar', 13000, 2],
                    ],
                ],
                [
                    'name' => 'Roti Sobek Pandan', 'slug' => 'roti-sobek-pandan',
                    'desc' => 'Roti sobek wangi pandan dengan tekstur fluffy.',
                    'price' => 32000, 'sort' => 3, 'variants' => [],
                ],
                [
                    'name' => 'Roti Sobek Cokelat Keju', 'slug' => 'roti-sobek-cokelat-keju',
                    'desc' => 'Roti sobek isi cokelat dan keju yang meleleh.',
                    'price' => 38000, 'sort' => 4, 'variants' => [],
                ],
                [
                    'name' => 'Roti Brioche', 'slug' => 'roti-brioche',
                    'desc' => 'Brioche Prancis dengan butter premium, lembut dan kaya rasa.',
                    'price' => 42000, 'sort' => 5, 'variants' => [],
                ],
                [
                    'name' => 'Roti Susu Jepang', 'slug' => 'roti-susu-jepang',
                    'desc' => 'Hokkaido milk bread, super lembut dengan tekstur cloud-like.',
                    'price' => 45000, 'sort' => 6, 'variants' => [],
                ],
                [
                    'name' => 'Roti Kasur Cokelat', 'slug' => 'roti-kasur-cokelat',
                    'desc' => 'Roti kasur isi pasta cokelat yang manis dan harum.',
                    'price' => 12000, 'sort' => 7, 'variants' => [],
                ],
                [
                    'name' => 'Roti Kasur Keju', 'slug' => 'roti-kasur-keju',
                    'desc' => 'Roti kasur isi keju cheddar yang creamy dan gurih.',
                    'price' => 12000, 'sort' => 8, 'variants' => [],
                ],
                [
                    'name' => 'Roti Unyil Cokelat', 'slug' => 'roti-unyil-cokelat',
                    'desc' => 'Roti mini isi cokelat, cocok sebagai camilan.',
                    'price' => 8000, 'sort' => 9, 'variants' => [],
                ],
                [
                    'name' => 'Roti Unyil Keju', 'slug' => 'roti-unyil-keju',
                    'desc' => 'Roti mini isi keju, gurih di setiap gigitan.',
                    'price' => 8000, 'sort' => 10, 'variants' => [],
                ],
                [
                    'name' => 'Roti Abon Sapi', 'slug' => 'roti-abon-sapi',
                    'desc' => 'Roti empuk dengan topping abon sapi dan mayonaise.',
                    'price' => 15000, 'sort' => 11, 'variants' => [],
                ],
                [
                    'name' => 'Roti Srikaya', 'slug' => 'roti-srikaya',
                    'desc' => 'Roti oles kaya srikaya pandan, sarapan favorit klasik.',
                    'price' => 10000, 'sort' => 12, 'variants' => [],
                ],
                [
                    'name' => 'Roti Bagel Original', 'slug' => 'roti-bagel-original',
                    'desc' => 'Bagel autentik dengan tekstur kenyal sempurna.',
                    'price' => 25000, 'sort' => 13, 'variants' => [],
                ],
                [
                    'name' => 'Roti Focaccia Rosemary', 'slug' => 'roti-focaccia-rosemary',
                    'desc' => 'Focaccia Italia dengan olive oil dan rosemary segar.',
                    'price' => 35000, 'sort' => 14, 'variants' => [],
                ],
                [
                    'name' => 'Roti Pisang Cokelat', 'slug' => 'roti-pisang-cokelat',
                    'desc' => 'Roti isi pisang cavendish dan cokelat leleh.',
                    'price' => 14000, 'sort' => 15, 'variants' => [],
                ],
            ],

            'pastry-croissant' => [
                [
                    'name' => 'Croissant Polos', 'slug' => 'croissant-polos',
                    'desc' => 'Croissant butter berlapis, renyah di luar lembut di dalam.',
                    'price' => 28000, 'sort' => 1, 'variants' => [],
                ],
                [
                    'name' => 'Croissant Keju', 'slug' => 'croissant-keju',
                    'desc' => 'Croissant isi keju cheddar meleleh yang gurih.',
                    'price' => 32000, 'sort' => 2, 'variants' => [],
                ],
                [
                    'name' => 'Croissant Cokelat', 'slug' => 'croissant-cokelat',
                    'desc' => 'Croissant isi ganache cokelat dark premium.',
                    'price' => 32000, 'sort' => 3, 'variants' => [],
                ],
                [
                    'name' => 'Pain au Chocolat', 'slug' => 'pain-au-chocolat',
                    'desc' => 'Pastry berlapis dengan batangan cokelat Belgia di dalamnya.',
                    'price' => 35000, 'sort' => 4, 'variants' => [],
                ],
                [
                    'name' => 'Danish Pisang', 'slug' => 'danish-pisang',
                    'desc' => 'Danish pastry topped with pisang caramel dan cream cheese.',
                    'price' => 30000, 'sort' => 5, 'variants' => [],
                ],
                [
                    'name' => 'Danish Apple Cinnamon', 'slug' => 'danish-apple-cinnamon',
                    'desc' => 'Danish dengan apel kayu manis hangat dan glaze manis.',
                    'price' => 30000, 'sort' => 6, 'variants' => [],
                ],
                [
                    'name' => 'Eclair Vanilla', 'slug' => 'eclair-vanilla',
                    'desc' => 'Eclair choux pastry isi krim vanilla Madagaskar.',
                    'price' => 22000, 'sort' => 7, 'variants' => [],
                ],
                [
                    'name' => 'Eclair Cokelat', 'slug' => 'eclair-cokelat',
                    'desc' => 'Eclair choux pastry isi krim cokelat dengan glazur ganache.',
                    'price' => 22000, 'sort' => 8, 'variants' => [],
                ],
                [
                    'name' => 'Mille-Feuille', 'slug' => 'mille-feuille',
                    'desc' => 'Seribu lapis pastry dengan krim patissiere dan stroberi.',
                    'price' => 45000, 'sort' => 9, 'variants' => [],
                ],
                [
                    'name' => 'Tart Buah Segar', 'slug' => 'tart-buah-segar',
                    'desc' => 'Tart base crumble dengan krim dan irisan buah segar musiman.',
                    'price' => 40000, 'sort' => 10, 'variants' => [],
                ],
                [
                    'name' => 'Scone Original', 'slug' => 'scone-original',
                    'desc' => 'Scone Inggris klasik, sempurna dengan butter dan jam.',
                    'price' => 18000, 'sort' => 11, 'variants' => [],
                ],
                [
                    'name' => 'Scone Blueberry', 'slug' => 'scone-blueberry',
                    'desc' => 'Scone dengan blueberry segar di dalam adonan.',
                    'price' => 22000, 'sort' => 12, 'variants' => [],
                ],
                [
                    'name' => 'Cinnamon Roll', 'slug' => 'cinnamon-roll',
                    'desc' => 'Gulungan kayu manis empuk dengan glaze gula putih.',
                    'price' => 28000, 'sort' => 13, 'variants' => [],
                ],
                [
                    'name' => 'Cinnamon Roll Cream Cheese', 'slug' => 'cinnamon-roll-cream-cheese',
                    'desc' => 'Cinnamon roll dengan frosting cream cheese yang kaya.',
                    'price' => 33000, 'sort' => 14, 'variants' => [],
                ],
                [
                    'name' => 'Palmier', 'slug' => 'palmier',
                    'desc' => 'Palmier puff pastry berbentuk hati, renyah dan manis.',
                    'price' => 12000, 'sort' => 15, 'variants' => [],
                ],
            ],

            'kue-cake' => [
                [
                    'name' => 'Lapis Legit', 'slug' => 'lapis-legit',
                    'desc' => 'Kue lapis spekuk premium dengan 18 lapisan tipis.',
                    'price' => 35000, 'sort' => 1,
                    'variants' => [
                        ['Slice', 'slice', 0,      1],
                        ['Loaf',  'loaf',  130000,  2],
                    ],
                ],
                [
                    'name' => 'Bolu Pandan', 'slug' => 'bolu-pandan',
                    'desc' => 'Bolu wangi pandan dengan tekstur lembut dan moist.',
                    'price' => 20000, 'sort' => 2,
                    'variants' => [
                        ['Slice', 'slice', 0,     1],
                        ['Loaf',  'loaf',  60000,  2],
                    ],
                ],
                [
                    'name' => 'Bolu Cokelat', 'slug' => 'bolu-cokelat',
                    'desc' => 'Bolu cokelat moist dengan taburan choco chips.',
                    'price' => 22000, 'sort' => 3,
                    'variants' => [
                        ['Slice', 'slice', 0,     1],
                        ['Loaf',  'loaf',  65000,  2],
                    ],
                ],
                [
                    'name' => 'Cheesecake New York', 'slug' => 'cheesecake-new-york',
                    'desc' => 'NY cheesecake klasik dengan cream cheese berkualitas tinggi.',
                    'price' => 45000, 'sort' => 4,
                    'variants' => [
                        ['Slice', 'slice', 0,      1],
                        ['Whole', 'whole', 270000,  2],
                    ],
                ],
                [
                    'name' => 'Cheesecake Blueberry', 'slug' => 'cheesecake-blueberry',
                    'desc' => 'Cheesecake dengan topping blueberry compote segar.',
                    'price' => 48000, 'sort' => 5,
                    'variants' => [
                        ['Slice', 'slice', 0,      1],
                        ['Whole', 'whole', 290000,  2],
                    ],
                ],
                [
                    'name' => 'Tart Lemon', 'slug' => 'tart-lemon',
                    'desc' => 'Tart lemon segar dengan curd asam manis dan meringue.',
                    'price' => 38000, 'sort' => 6,
                    'variants' => [
                        ['Slice', 'slice', 0,      1],
                        ['Whole', 'whole', 230000,  2],
                    ],
                ],
                [
                    'name' => 'Red Velvet Cake', 'slug' => 'red-velvet-cake',
                    'desc' => 'Red velvet tiga lapis dengan cream cheese frosting.',
                    'price' => 50000, 'sort' => 7,
                    'variants' => [
                        ['Slice', 'slice', 0,      1],
                        ['Whole', 'whole', 320000,  2],
                    ],
                ],
                [
                    'name' => 'Black Forest Cake', 'slug' => 'black-forest-cake',
                    'desc' => 'Kue cokelat klasik dengan kirsch cherry dan whipped cream.',
                    'price' => 50000, 'sort' => 8,
                    'variants' => [
                        ['Slice', 'slice', 0,      1],
                        ['Whole', 'whole', 320000,  2],
                    ],
                ],
                [
                    'name' => 'Carrot Cake', 'slug' => 'carrot-cake',
                    'desc' => 'Carrot cake moist dengan cream cheese frosting dan walnut.',
                    'price' => 42000, 'sort' => 9,
                    'variants' => [
                        ['Slice', 'slice', 0,      1],
                        ['Whole', 'whole', 260000,  2],
                    ],
                ],
                [
                    'name' => 'Opera Cake', 'slug' => 'opera-cake',
                    'desc' => 'Opera cake berlapis dengan kopi dan ganache cokelat.',
                    'price' => 55000, 'sort' => 10,
                    'variants' => [
                        ['Slice', 'slice', 0,      1],
                        ['Whole', 'whole', 350000,  2],
                    ],
                ],
                [
                    'name' => 'Tiramisu', 'slug' => 'tiramisu',
                    'desc' => 'Tiramisu Italia otentik dengan mascarpone dan espresso.',
                    'price' => 45000, 'sort' => 11, 'variants' => [],
                ],
                [
                    'name' => 'Mochi Cokelat', 'slug' => 'mochi-cokelat',
                    'desc' => 'Mochi lembut isi ganache cokelat dark.',
                    'price' => 15000, 'sort' => 12, 'variants' => [],
                ],
                [
                    'name' => 'Klepon Cake', 'slug' => 'klepon-cake',
                    'desc' => 'Kue terinspirasi klepon dengan pandan gula merah.',
                    'price' => 28000, 'sort' => 13,
                    'variants' => [
                        ['Slice', 'slice', 0,     1],
                        ['Loaf',  'loaf',  75000,  2],
                    ],
                ],
                [
                    'name' => 'Kue Sus Vla', 'slug' => 'kue-sus-vla',
                    'desc' => 'Sus choux pastry isi vla vanilla creamy.',
                    'price' => 8000, 'sort' => 14, 'variants' => [],
                ],
                [
                    'name' => 'Puding Cokelat', 'slug' => 'puding-cokelat',
                    'desc' => 'Puding cokelat smooth dengan saus vanilla.',
                    'price' => 18000, 'sort' => 15, 'variants' => [],
                ],
            ],

            'cookies-snack' => [
                [
                    'name' => 'Cookies Cokelat Chip', 'slug' => 'cookies-cokelat-chip',
                    'desc' => 'Cookies classic dengan choco chips premium melimpah.',
                    'price' => 35000, 'sort' => 1,
                    'variants' => [
                        ['100gr', '100gr', 0,     1],
                        ['200gr', '200gr', 30000,  2],
                    ],
                ],
                [
                    'name' => 'Cookies Almond', 'slug' => 'cookies-almond',
                    'desc' => 'Cookies butter dengan topping almond panggang renyah.',
                    'price' => 40000, 'sort' => 2,
                    'variants' => [
                        ['100gr', '100gr', 0,     1],
                        ['200gr', '200gr', 35000,  2],
                    ],
                ],
                [
                    'name' => 'Cookies Oatmeal Raisin', 'slug' => 'cookies-oatmeal-raisin',
                    'desc' => 'Cookies sehat oatmeal dengan kismis manis.',
                    'price' => 35000, 'sort' => 3,
                    'variants' => [
                        ['100gr', '100gr', 0,     1],
                        ['200gr', '200gr', 30000,  2],
                    ],
                ],
                [
                    'name' => 'Nastar Nanas', 'slug' => 'nastar-nanas',
                    'desc' => 'Nastar klasik isi selai nanas homemade, lumer di mulut.',
                    'price' => 45000, 'sort' => 4,
                    'variants' => [
                        ['100gr', '100gr', 0,     1],
                        ['200gr', '200gr', 40000,  2],
                    ],
                ],
                [
                    'name' => 'Kaastengels', 'slug' => 'kaastengels',
                    'desc' => 'Kue keju Belanda dengan parmesan dan edam berlimpah.',
                    'price' => 50000, 'sort' => 5,
                    'variants' => [
                        ['100gr', '100gr', 0,     1],
                        ['200gr', '200gr', 45000,  2],
                    ],
                ],
                [
                    'name' => 'Putri Salju', 'slug' => 'putri-salju',
                    'desc' => 'Kue putri salju kacang mete dengan balutan gula halus.',
                    'price' => 40000, 'sort' => 6,
                    'variants' => [
                        ['100gr', '100gr', 0,     1],
                        ['200gr', '200gr', 35000,  2],
                    ],
                ],
                [
                    'name' => 'Brownies Original', 'slug' => 'brownies-original',
                    'desc' => 'Brownies fudgy dark chocolate yang padat dan kaya.',
                    'price' => 25000, 'sort' => 7,
                    'variants' => [
                        ['Slice', 'slice', 0,     1],
                        ['Box',   'box',   95000,  2],
                    ],
                ],
                [
                    'name' => 'Brownies Keju', 'slug' => 'brownies-keju',
                    'desc' => 'Brownies cokelat dengan swirl cream cheese.',
                    'price' => 28000, 'sort' => 8,
                    'variants' => [
                        ['Slice', 'slice', 0,      1],
                        ['Box',   'box',   110000,  2],
                    ],
                ],
                [
                    'name' => 'Muffin Blueberry', 'slug' => 'muffin-blueberry',
                    'desc' => 'Muffin fluffy dengan blueberry segar meledak di setiap gigitan.',
                    'price' => 18000, 'sort' => 9, 'variants' => [],
                ],
                [
                    'name' => 'Muffin Cokelat Chip', 'slug' => 'muffin-cokelat-chip',
                    'desc' => 'Muffin cokelat dengan double choco chips.',
                    'price' => 18000, 'sort' => 10, 'variants' => [],
                ],
            ],

            'minuman' => [
                [
                    'name' => 'Kopi Americano', 'slug' => 'kopi-americano',
                    'desc' => 'Espresso double shot dengan air panas, bold dan bersih.',
                    'price' => 22000, 'sort' => 1,
                    'variants' => [
                        ['Hot', 'hot', 0,    1],
                        ['Ice', 'ice', 3000, 2],
                    ],
                ],
                [
                    'name' => 'Kopi Latte', 'slug' => 'kopi-latte',
                    'desc' => 'Espresso dengan steamed milk dan microfoam lembut.',
                    'price' => 28000, 'sort' => 2,
                    'variants' => [
                        ['Hot', 'hot', 0,    1],
                        ['Ice', 'ice', 3000, 2],
                    ],
                ],
                [
                    'name' => 'Cappuccino', 'slug' => 'cappuccino',
                    'desc' => 'Cappuccino klasik dengan milk foam tebal dan cinnamon.',
                    'price' => 28000, 'sort' => 3,
                    'variants' => [
                        ['Hot', 'hot', 0,    1],
                        ['Ice', 'ice', 3000, 2],
                    ],
                ],
                [
                    'name' => 'Teh Tarik', 'slug' => 'teh-tarik',
                    'desc' => 'Teh tarik khas dengan susu kental manis yang creamy.',
                    'price' => 18000, 'sort' => 4,
                    'variants' => [
                        ['Hot', 'hot', 0,    1],
                        ['Ice', 'ice', 3000, 2],
                    ],
                ],
                [
                    'name' => 'Matcha Latte', 'slug' => 'matcha-latte',
                    'desc' => 'Matcha grade premium dengan steamed milk.',
                    'price' => 32000, 'sort' => 5,
                    'variants' => [
                        ['Hot', 'hot', 0,    1],
                        ['Ice', 'ice', 3000, 2],
                    ],
                ],
                [
                    'name' => 'Cokelat Hangat', 'slug' => 'cokelat-hangat',
                    'desc' => 'Hot chocolate dengan cokelat Belgia 70% kakao.',
                    'price' => 25000, 'sort' => 6,
                    'variants' => [
                        ['Hot', 'hot', 0,    1],
                        ['Ice', 'ice', 3000, 2],
                    ],
                ],
                [
                    'name' => 'Jus Jeruk Segar', 'slug' => 'jus-jeruk-segar',
                    'desc' => 'Jus jeruk peras segar tanpa pengawet.',
                    'price' => 18000, 'sort' => 7, 'variants' => [],
                ],
                [
                    'name' => 'Lemonade', 'slug' => 'lemonade',
                    'desc' => 'Lemonade segar dengan perasan lemon asli dan madu.',
                    'price' => 20000, 'sort' => 8, 'variants' => [],
                ],
                [
                    'name' => 'Air Mineral', 'slug' => 'air-mineral',
                    'desc' => 'Air mineral kemasan 600ml.',
                    'price' => 8000, 'sort' => 9, 'variants' => [],
                ],
                [
                    'name' => 'Susu Segar', 'slug' => 'susu-segar',
                    'desc' => 'Susu segar murni dari peternak lokal pilihan.',
                    'price' => 15000, 'sort' => 10,
                    'variants' => [
                        ['Hot', 'hot', 0,    1],
                        ['Ice', 'ice', 2000, 2],
                    ],
                ],
            ],

            'sandwich-savory' => [
                [
                    'name' => 'Sandwich Club', 'slug' => 'sandwich-club',
                    'desc' => 'Club sandwich berlapis ayam, bacon, selada, dan tomat.',
                    'price' => 45000, 'sort' => 1, 'variants' => [],
                ],
                [
                    'name' => 'Sandwich BLT', 'slug' => 'sandwich-blt',
                    'desc' => 'Bacon, lettuce, dan tomato segar dengan mayo.',
                    'price' => 38000, 'sort' => 2, 'variants' => [],
                ],
                [
                    'name' => 'Quiche Lorraine', 'slug' => 'quiche-lorraine',
                    'desc' => 'Quiche klasik dengan bacon, keju gruyere, dan custard telur.',
                    'price' => 42000, 'sort' => 3, 'variants' => [],
                ],
                [
                    'name' => 'Pie Ayam', 'slug' => 'pie-ayam',
                    'desc' => 'Pie kulit renyah isi ayam suwir dan sayuran saus krim.',
                    'price' => 35000, 'sort' => 4, 'variants' => [],
                ],
                [
                    'name' => 'Sausage Roll', 'slug' => 'sausage-roll',
                    'desc' => 'Sosis sapi premium dibungkus puff pastry renyah.',
                    'price' => 25000, 'sort' => 5, 'variants' => [],
                ],
            ],
        ];
    }

    // ── USD Price Map ────────────────────────────────────────────
    // Base prices in USD per item slug. Variant deltas are scaled
    // proportionally from the IDR delta/base ratio applied to the USD base.

    private static function getUsdPrices(): array
    {
        return [
            // Roti
            'roti-tawar-putih'          => 2.50,
            'roti-tawar-gandum'         => 3.00,
            'roti-sobek-pandan'         => 4.00,
            'roti-sobek-cokelat-keju'   => 5.00,
            'roti-brioche'              => 5.50,
            'roti-susu-jepang'          => 6.00,
            'roti-kasur-cokelat'        => 1.50,
            'roti-kasur-keju'           => 1.50,
            'roti-unyil-cokelat'        => 1.00,
            'roti-unyil-keju'           => 1.00,
            'roti-abon-sapi'            => 2.00,
            'roti-srikaya'              => 1.25,
            'roti-bagel-original'       => 3.25,
            'roti-focaccia-rosemary'    => 4.50,
            'roti-pisang-cokelat'       => 1.75,
            // Pastry & Croissant
            'croissant-polos'           => 3.50,
            'croissant-keju'            => 4.00,
            'croissant-cokelat'         => 4.00,
            'pain-au-chocolat'          => 4.50,
            'danish-pisang'             => 3.75,
            'danish-apple-cinnamon'     => 3.75,
            'eclair-vanilla'            => 2.75,
            'eclair-cokelat'            => 2.75,
            'mille-feuille'             => 5.75,
            'tart-buah-segar'           => 5.25,
            'scone-original'            => 2.25,
            'scone-blueberry'           => 2.75,
            'cinnamon-roll'             => 3.50,
            'cinnamon-roll-cream-cheese'=> 4.25,
            'palmier'                   => 1.50,
            // Kue & Cake
            'lapis-legit'               => 4.50,
            'bolu-pandan'               => 2.50,
            'bolu-cokelat'              => 2.75,
            'cheesecake-new-york'       => 5.75,
            'cheesecake-blueberry'      => 6.25,
            'tart-lemon'                => 5.00,
            'red-velvet-cake'           => 6.50,
            'black-forest-cake'         => 6.50,
            'carrot-cake'               => 5.50,
            'opera-cake'                => 7.00,
            'tiramisu'                  => 5.75,
            'mochi-cokelat'             => 2.00,
            'klepon-cake'               => 3.50,
            'kue-sus-vla'               => 1.00,
            'puding-cokelat'            => 2.25,
            // Cookies & Snack
            'cookies-cokelat-chip'      => 4.50,
            'cookies-almond'            => 5.25,
            'cookies-oatmeal-raisin'    => 4.50,
            'nastar-nanas'              => 5.75,
            'kaastengels'               => 6.50,
            'putri-salju'               => 5.25,
            'brownies-original'         => 3.25,
            'brownies-keju'             => 3.50,
            'muffin-blueberry'          => 2.25,
            'muffin-cokelat-chip'       => 2.25,
            // Minuman
            'kopi-americano'            => 2.75,
            'kopi-latte'                => 3.50,
            'cappuccino'                => 3.50,
            'teh-tarik'                 => 2.25,
            'matcha-latte'              => 4.00,
            'cokelat-hangat'            => 3.25,
            'jus-jeruk-segar'           => 2.25,
            'lemonade'                  => 2.50,
            'air-mineral'               => 1.00,
            'susu-segar'                => 1.75,
            // Sandwich & Savory
            'sandwich-club'             => 5.75,
            'sandwich-blt'              => 4.75,
            'quiche-lorraine'           => 5.50,
            'pie-ayam'                  => 4.50,
            'sausage-roll'              => 3.25,
        ];
    }

    // Multiplier relative to USD for other non-IDR currencies
    private static function getCurrencyFactor(string $currency): float
    {
        return match (strtoupper($currency)) {
            'SGD'   => 1.35,
            'AUD'   => 1.55,
            'USD'   => 1.00,
            default => 1.00, // unknown non-IDR: use USD scale
        };
    }

    // ── Reset & Seed ─────────────────────────────────────────────
    // Base prices are always seeded in IDR into menu_items.
    // For every branch whose currency ≠ IDR, branch_menu_overrides and
    // branch_menu_variant_overrides are created automatically using the
    // USD price map × the branch's currency factor.

    public function resetAndSeed(): array
    {
        $pdo = Database::getInstance();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Read branch currencies before the transaction (read-only query)
        $branchRows = $pdo->query(
            "SELECT b.id AS branch_id,
                    UPPER(COALESCE(bs.setting_val, 'IDR')) AS currency
             FROM branches b
             LEFT JOIN branch_settings bs
               ON bs.branch_id = b.id AND bs.setting_key = 'currency'
             WHERE b.is_active = 1"
        )->fetchAll();

        // Separate IDR branches (no overrides needed) from non-IDR branches
        $nonIdrBranches = [];  // branch_id => currency
        foreach ($branchRows as $row) {
            if ($row['currency'] !== 'IDR') {
                $nonIdrBranches[(int)$row['branch_id']] = $row['currency'];
            }
        }

        $usdPrices = self::getUsdPrices();

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->beginTransaction();

        try {
            // ── Clear order tables ────────────────────────────────
            $pdo->exec('DELETE FROM order_status_logs');
            $pdo->exec('DELETE FROM order_items');
            $pdo->exec('DELETE FROM orders');
            $pdo->exec('DELETE FROM cart_items');
            $pdo->exec('DELETE FROM carts');

            // ── Clear product tables ──────────────────────────────
            $pdo->exec('DELETE FROM menu_item_toppings');
            $pdo->exec('DELETE FROM branch_menu_variant_overrides');
            $pdo->exec('DELETE FROM branch_menu_overrides');
            $pdo->exec('DELETE FROM menu_item_variants');
            $pdo->exec('DELETE FROM menu_items');
            $pdo->exec('DELETE FROM menu_toppings');
            $pdo->exec('DELETE FROM menu_categories');

            // ── Prepared statements ───────────────────────────────
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

            // ── Seed categories ───────────────────────────────────
            $categoryMap = [];
            foreach (self::getCategories() as [$name, $slug, $desc, $sort]) {
                $stmtCat->execute([':name' => $name, ':slug' => $slug, ':desc' => $desc, ':sort' => $sort]);
                $categoryMap[$slug] = (int)$pdo->lastInsertId();
            }

            // ── Seed items & variants (IDR base) ──────────────────
            // Also collect data needed for branch overrides
            $seededItems    = []; // item_id => ['idr' => float, 'usd' => float]
            $seededVariants = []; // variant_id => ['idr_delta' => float, 'idr_base' => float, 'usd_base' => float]

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
                        ':price'  => $idrBase,  // always IDR in base table
                        ':sort'   => $item['sort'],
                    ]);
                    $itemId = (int)$pdo->lastInsertId();
                    $seededItems[$itemId] = ['idr' => $idrBase, 'usd' => $usdBase];

                    foreach ($item['variants'] as [$label, $vSlug, $idrDelta, $vSort]) {
                        $stmtVariant->execute([
                            ':item_id' => $itemId,
                            ':label'   => $label,
                            ':slug'    => $vSlug,
                            ':delta'   => $idrDelta,  // IDR delta in base table
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

            // ── Branch overrides for non-IDR branches ─────────────
            foreach ($nonIdrBranches as $branchId => $currency) {
                $factor = self::getCurrencyFactor($currency);

                foreach ($seededItems as $itemId => $prices) {
                    $convertedPrice = round($prices['usd'] * $factor, 2);
                    $stmtItemOvr->execute([
                        ':branch_id' => $branchId,
                        ':item_id'   => $itemId,
                        ':price'     => $convertedPrice,
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
                'success'          => true,
                'categories'       => count($categoryMap),
                'items'            => count($seededItems),
                'variants'         => count($seededVariants),
                'override_branches' => $nonIdrBranches,  // branch_id => currency
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
