<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class FruitTemplatePlugin implements PluginInterface
{
    public function getName(): string    { return 'Fruit Store Template'; }
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
                'url'   => '/dashboard/super/fruit-template.php',
                'icon'  => '🍉',
                'label' => 'Fruit Template',
            ];
        }

        return $items;
    }

    // ── Seed Data ────────────────────────────────────────────────

    /**
     * Each entry: [name, slug, description, sort_order]
     */
    public static function getCategories(): array
    {
        return [
            ['Buah Segar Lokal', 'buah-segar-lokal', 'Buah segar pilihan dari kebun lokal terbaik',       1],
            ['Buah Impor',       'buah-impor',        'Buah impor premium berkualitas internasional',       2],
            ['Jus Segar',        'jus-segar',         'Jus buah segar diperas langsung tanpa pengawet',    3],
            ['Smoothie & Bowl',  'smoothie-bowl',     'Smoothie sehat dan acai bowl penuh nutrisi',        4],
            ['Salad & Platter',  'salad-platter',     'Sajian salad buah dan platter untuk berbagi',       5],
        ];
    }

    /**
     * Each item: [name, slug, description, price (IDR), sort_order, variants[]]
     * Each variant: [label, slug, price_delta (IDR), sort_order]
     */
    public static function getMenuItems(): array
    {
        return [
            'buah-segar-lokal' => [
                [
                    'name' => 'Mangga Harum Manis', 'slug' => 'mangga-harum-manis',
                    'desc' => 'Mangga harum manis segar dari Probolinggo, manis dan harum.',
                    'price' => 25000, 'sort' => 1,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   20000,  2],
                    ],
                ],
                [
                    'name' => 'Mangga Gedong Gincu', 'slug' => 'mangga-gedong-gincu',
                    'desc' => 'Mangga gedong gincu khas Indramayu, daging tebal dan manis.',
                    'price' => 30000, 'sort' => 2,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   25000,  2],
                    ],
                ],
                [
                    'name' => 'Pisang Cavendish', 'slug' => 'pisang-cavendish',
                    'desc' => 'Pisang cavendish premium, lembut manis dan kaya potasium.',
                    'price' => 18000, 'sort' => 3,
                    'variants' => [
                        ['1 Sisir', '1-sisir', 0,     1],
                        ['2 Sisir', '2-sisir', 14000,  2],
                    ],
                ],
                [
                    'name' => 'Pisang Ambon', 'slug' => 'pisang-ambon',
                    'desc' => 'Pisang ambon matang sempurna, manis alami dan lembut.',
                    'price' => 15000, 'sort' => 4,
                    'variants' => [
                        ['1 Sisir', '1-sisir', 0,     1],
                        ['2 Sisir', '2-sisir', 12000,  2],
                    ],
                ],
                [
                    'name' => 'Semangka Merah', 'slug' => 'semangka-merah',
                    'desc' => 'Semangka merah segar tanpa biji, manis dan menyegarkan.',
                    'price' => 15000, 'sort' => 5,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   12000,  2],
                    ],
                ],
                [
                    'name' => 'Melon Kuning', 'slug' => 'melon-kuning',
                    'desc' => 'Melon kuning Golden, daging tebal manis dan harum.',
                    'price' => 20000, 'sort' => 6,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   16000,  2],
                    ],
                ],
                [
                    'name' => 'Pepaya California', 'slug' => 'pepaya-california',
                    'desc' => 'Pepaya california matang, manis dan kaya vitamin C.',
                    'price' => 18000, 'sort' => 7,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   14000,  2],
                    ],
                ],
                [
                    'name' => 'Rambutan', 'slug' => 'rambutan',
                    'desc' => 'Rambutan segar berbulu merah, manis berair dan segar.',
                    'price' => 15000, 'sort' => 8,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 12000,  2],
                    ],
                ],
                [
                    'name' => 'Salak Pondoh', 'slug' => 'salak-pondoh',
                    'desc' => 'Salak pondoh Sleman, manis legit tanpa rasa sepat.',
                    'price' => 12000, 'sort' => 9,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 10000,  2],
                    ],
                ],
                [
                    'name' => 'Jeruk Siam', 'slug' => 'jeruk-siam',
                    'desc' => 'Jeruk siam Pontianak, segar asam manis penuh vitamin.',
                    'price' => 20000, 'sort' => 10,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   16000,  2],
                    ],
                ],
                [
                    'name' => 'Jeruk Bali', 'slug' => 'jeruk-bali',
                    'desc' => 'Jeruk bali besar, segmen tebal manis segar.',
                    'price' => 35000, 'sort' => 11, 'variants' => [],
                ],
                [
                    'name' => 'Nanas Madu', 'slug' => 'nanas-madu',
                    'desc' => 'Nanas madu Subang, manis tanpa rasa asam menyengat.',
                    'price' => 20000, 'sort' => 12, 'variants' => [],
                ],
                [
                    'name' => 'Belimbing Manis', 'slug' => 'belimbing-manis',
                    'desc' => 'Belimbing bintang manis renyah, segar dan rendah kalori.',
                    'price' => 12000, 'sort' => 13,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 10000,  2],
                    ],
                ],
                [
                    'name' => 'Jambu Air Merah', 'slug' => 'jambu-air-merah',
                    'desc' => 'Jambu air merah renyah, segar berair dan warna cantik.',
                    'price' => 12000, 'sort' => 14,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 10000,  2],
                    ],
                ],
                [
                    'name' => 'Alpukat Mentega', 'slug' => 'alpukat-mentega',
                    'desc' => 'Alpukat mentega lokal, creamy dan kaya lemak sehat.',
                    'price' => 18000, 'sort' => 15, 'variants' => [],
                ],
            ],

            'buah-impor' => [
                [
                    'name' => 'Apel Fuji', 'slug' => 'apel-fuji',
                    'desc' => 'Apel Fuji impor Jepang, renyah manis dengan aroma khas.',
                    'price' => 35000, 'sort' => 1,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   30000,  2],
                    ],
                ],
                [
                    'name' => 'Apel Granny Smith', 'slug' => 'apel-granny-smith',
                    'desc' => 'Apel hijau Granny Smith, renyah segar dengan rasa asam manis.',
                    'price' => 35000, 'sort' => 2,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   30000,  2],
                    ],
                ],
                [
                    'name' => 'Anggur Red Globe', 'slug' => 'anggur-red-globe',
                    'desc' => 'Anggur Red Globe besar berbiji, manis dan berair.',
                    'price' => 45000, 'sort' => 3,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 40000,  2],
                    ],
                ],
                [
                    'name' => 'Anggur Shine Muscat', 'slug' => 'anggur-shine-muscat',
                    'desc' => 'Anggur Shine Muscat premium tanpa biji, manis beraroma bunga.',
                    'price' => 85000, 'sort' => 4,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 80000,  2],
                    ],
                ],
                [
                    'name' => 'Pir Yali', 'slug' => 'pir-yali',
                    'desc' => 'Pir Yali China, renyah segar dengan rasa manis ringan.',
                    'price' => 30000, 'sort' => 5,
                    'variants' => [
                        ['500gr', '500gr', 0,     1],
                        ['1kg',   '1kg',   25000,  2],
                    ],
                ],
                [
                    'name' => 'Stroberi', 'slug' => 'stroberi',
                    'desc' => 'Stroberi segar merah cerah, manis asam dan kaya antioksidan.',
                    'price' => 35000, 'sort' => 6,
                    'variants' => [
                        ['250gr', '250gr', 0,     1],
                        ['500gr', '500gr', 30000,  2],
                    ],
                ],
                [
                    'name' => 'Blueberry', 'slug' => 'blueberry',
                    'desc' => 'Blueberry impor segar, kaya antosianin dan vitamin.',
                    'price' => 45000, 'sort' => 7,
                    'variants' => [
                        ['125gr', '125gr', 0,     1],
                        ['250gr', '250gr', 40000,  2],
                    ],
                ],
                [
                    'name' => 'Ceri', 'slug' => 'ceri',
                    'desc' => 'Ceri merah impor premium, manis asam segar.',
                    'price' => 55000, 'sort' => 8,
                    'variants' => [
                        ['100gr', '100gr', 0,     1],
                        ['200gr', '200gr', 50000,  2],
                    ],
                ],
                [
                    'name' => 'Kiwi', 'slug' => 'kiwi',
                    'desc' => 'Kiwi hijau Selandia Baru, segar asam manis kaya vitamin C.',
                    'price' => 25000, 'sort' => 9,
                    'variants' => [
                        ['3 Buah', '3-buah', 0,     1],
                        ['6 Buah', '6-buah', 22000,  2],
                    ],
                ],
                [
                    'name' => 'Lemon', 'slug' => 'lemon',
                    'desc' => 'Lemon segar impor, asam segar untuk minuman dan masakan.',
                    'price' => 15000, 'sort' => 10,
                    'variants' => [
                        ['3 Buah', '3-buah', 0,     1],
                        ['6 Buah', '6-buah', 12000,  2],
                    ],
                ],
                [
                    'name' => 'Alpukat Hass', 'slug' => 'alpukat-hass',
                    'desc' => 'Alpukat Hass impor, creamy dan kaya lemak tak jenuh.',
                    'price' => 35000, 'sort' => 11, 'variants' => [],
                ],
                [
                    'name' => 'Buah Naga Merah', 'slug' => 'buah-naga-merah',
                    'desc' => 'Buah naga merah segar, manis dan kaya serat.',
                    'price' => 25000, 'sort' => 12, 'variants' => [],
                ],
            ],

            'jus-segar' => [
                [
                    'name' => 'Jus Mangga', 'slug' => 'jus-mangga',
                    'desc' => 'Jus mangga harum manis segar peras, tanpa gula tambahan.',
                    'price' => 18000, 'sort' => 1,
                    'variants' => [
                        ['250ml', '250ml', 0,     1],
                        ['500ml', '500ml', 10000,  2],
                    ],
                ],
                [
                    'name' => 'Jus Jeruk Segar', 'slug' => 'jus-jeruk-segar',
                    'desc' => 'Jus jeruk peras segar asli tanpa pengawet.',
                    'price' => 18000, 'sort' => 2,
                    'variants' => [
                        ['250ml', '250ml', 0,     1],
                        ['500ml', '500ml', 10000,  2],
                    ],
                ],
                [
                    'name' => 'Jus Semangka', 'slug' => 'jus-semangka',
                    'desc' => 'Jus semangka merah segar, dingin menyegarkan.',
                    'price' => 15000, 'sort' => 3,
                    'variants' => [
                        ['250ml', '250ml', 0,    1],
                        ['500ml', '500ml', 8000,  2],
                    ],
                ],
                [
                    'name' => 'Jus Melon', 'slug' => 'jus-melon',
                    'desc' => 'Jus melon kuning segar, manis alami dan harum.',
                    'price' => 15000, 'sort' => 4,
                    'variants' => [
                        ['250ml', '250ml', 0,    1],
                        ['500ml', '500ml', 8000,  2],
                    ],
                ],
                [
                    'name' => 'Jus Jambu', 'slug' => 'jus-jambu',
                    'desc' => 'Jus jambu biji merah segar, kaya vitamin C.',
                    'price' => 16000, 'sort' => 5,
                    'variants' => [
                        ['250ml', '250ml', 0,    1],
                        ['500ml', '500ml', 9000,  2],
                    ],
                ],
                [
                    'name' => 'Jus Pepaya', 'slug' => 'jus-pepaya',
                    'desc' => 'Jus pepaya california lembut, baik untuk pencernaan.',
                    'price' => 15000, 'sort' => 6,
                    'variants' => [
                        ['250ml', '250ml', 0,    1],
                        ['500ml', '500ml', 8000,  2],
                    ],
                ],
                [
                    'name' => 'Jus Alpukat', 'slug' => 'jus-alpukat',
                    'desc' => 'Jus alpukat creamy dengan susu segar, kaya nutrisi.',
                    'price' => 22000, 'sort' => 7,
                    'variants' => [
                        ['250ml', '250ml', 0,     1],
                        ['500ml', '500ml', 12000,  2],
                    ],
                ],
                [
                    'name' => 'Jus Tomat', 'slug' => 'jus-tomat',
                    'desc' => 'Jus tomat segar kaya likopen, menyehatkan jantung.',
                    'price' => 15000, 'sort' => 8,
                    'variants' => [
                        ['250ml', '250ml', 0,    1],
                        ['500ml', '500ml', 8000,  2],
                    ],
                ],
                [
                    'name' => 'Jus Nanas', 'slug' => 'jus-nanas',
                    'desc' => 'Jus nanas madu segar, asam manis dan menyegarkan.',
                    'price' => 16000, 'sort' => 9,
                    'variants' => [
                        ['250ml', '250ml', 0,    1],
                        ['500ml', '500ml', 9000,  2],
                    ],
                ],
                [
                    'name' => 'Jus Belimbing', 'slug' => 'jus-belimbing',
                    'desc' => 'Jus belimbing manis segar, bantu turunkan tekanan darah.',
                    'price' => 16000, 'sort' => 10,
                    'variants' => [
                        ['250ml', '250ml', 0,    1],
                        ['500ml', '500ml', 9000,  2],
                    ],
                ],
                [
                    'name' => 'Jus Apel Hijau', 'slug' => 'jus-apel-hijau',
                    'desc' => 'Jus apel Granny Smith segar, asam segar kaya antioksidan.',
                    'price' => 20000, 'sort' => 11,
                    'variants' => [
                        ['250ml', '250ml', 0,     1],
                        ['500ml', '500ml', 11000,  2],
                    ],
                ],
                [
                    'name' => 'Jus Anggur Merah', 'slug' => 'jus-anggur-merah',
                    'desc' => 'Jus anggur merah segar peras, kaya resveratrol alami.',
                    'price' => 22000, 'sort' => 12,
                    'variants' => [
                        ['250ml', '250ml', 0,     1],
                        ['500ml', '500ml', 12000,  2],
                    ],
                ],
            ],

            'smoothie-bowl' => [
                [
                    'name' => 'Smoothie Mangga Pisang', 'slug' => 'smoothie-mangga-pisang',
                    'desc' => 'Blend mangga harum manis dan pisang cavendish, creamy segar.',
                    'price' => 28000, 'sort' => 1, 'variants' => [],
                ],
                [
                    'name' => 'Smoothie Stroberi', 'slug' => 'smoothie-stroberi',
                    'desc' => 'Smoothie stroberi segar dengan yogurt, kaya probiotik.',
                    'price' => 32000, 'sort' => 2, 'variants' => [],
                ],
                [
                    'name' => 'Smoothie Alpukat Cokelat', 'slug' => 'smoothie-alpukat-cokelat',
                    'desc' => 'Smoothie alpukat dengan dark chocolate, kaya lemak sehat.',
                    'price' => 35000, 'sort' => 3, 'variants' => [],
                ],
                [
                    'name' => 'Smoothie Blueberry', 'slug' => 'smoothie-blueberry',
                    'desc' => 'Smoothie blueberry ungu kaya antosianin dan vitamin.',
                    'price' => 38000, 'sort' => 4, 'variants' => [],
                ],
                [
                    'name' => 'Smoothie Semangka Mint', 'slug' => 'smoothie-semangka-mint',
                    'desc' => 'Blend semangka segar dengan daun mint, dingin menyegarkan.',
                    'price' => 28000, 'sort' => 5, 'variants' => [],
                ],
                [
                    'name' => 'Tropical Smoothie', 'slug' => 'tropical-smoothie',
                    'desc' => 'Blend mangga, nanas, dan pisang tropis yang eksotis.',
                    'price' => 32000, 'sort' => 6, 'variants' => [],
                ],
                [
                    'name' => 'Green Smoothie', 'slug' => 'green-smoothie',
                    'desc' => 'Smoothie hijau apel, kiwi, dan bayam, detoks alami.',
                    'price' => 30000, 'sort' => 7, 'variants' => [],
                ],
                [
                    'name' => 'Acai Bowl', 'slug' => 'acai-bowl',
                    'desc' => 'Acai berry bowl dengan granola, madu, dan topping buah segar.',
                    'price' => 55000, 'sort' => 8,
                    'variants' => [
                        ['Regular', 'regular', 0,     1],
                        ['Large',   'large',   15000,  2],
                    ],
                ],
                [
                    'name' => 'Smoothie Bowl Mangga', 'slug' => 'smoothie-bowl-mangga',
                    'desc' => 'Smoothie bowl mangga tropical dengan topping granola dan kelapa.',
                    'price' => 48000, 'sort' => 9,
                    'variants' => [
                        ['Regular', 'regular', 0,     1],
                        ['Large',   'large',   12000,  2],
                    ],
                ],
                [
                    'name' => 'Smoothie Bowl Pitaya', 'slug' => 'smoothie-bowl-pitaya',
                    'desc' => 'Smoothie bowl buah naga pink cantik, kaya serat dan antioksidan.',
                    'price' => 52000, 'sort' => 10,
                    'variants' => [
                        ['Regular', 'regular', 0,     1],
                        ['Large',   'large',   14000,  2],
                    ],
                ],
                [
                    'name' => 'Smoothie Bowl Blueberry', 'slug' => 'smoothie-bowl-blueberry',
                    'desc' => 'Smoothie bowl blueberry ungu dengan chia seeds dan almond.',
                    'price' => 50000, 'sort' => 11,
                    'variants' => [
                        ['Regular', 'regular', 0,     1],
                        ['Large',   'large',   13000,  2],
                    ],
                ],
            ],

            'salad-platter' => [
                [
                    'name' => 'Salad Buah Segar', 'slug' => 'salad-buah-segar',
                    'desc' => 'Campuran 8 jenis buah segar dipotong, disajikan dingin.',
                    'price' => 25000, 'sort' => 1,
                    'variants' => [
                        ['Small',  'small',  0,     1],
                        ['Large',  'large',  15000,  2],
                    ],
                ],
                [
                    'name' => 'Salad Buah Yogurt', 'slug' => 'salad-buah-yogurt',
                    'desc' => 'Salad buah segar dengan saus yogurt Greek dan madu.',
                    'price' => 30000, 'sort' => 2,
                    'variants' => [
                        ['Regular', 'regular', 0,     1],
                        ['Large',   'large',   15000,  2],
                    ],
                ],
                [
                    'name' => 'Salad Buah Keju', 'slug' => 'salad-buah-keju',
                    'desc' => 'Salad buah tropis dengan parutan keju cheddar dan mayonaise.',
                    'price' => 28000, 'sort' => 3, 'variants' => [],
                ],
                [
                    'name' => 'Tropical Fruit Platter', 'slug' => 'tropical-fruit-platter',
                    'desc' => 'Platter buah tropis premium untuk 4–6 orang, sajian istimewa.',
                    'price' => 85000, 'sort' => 4, 'variants' => [],
                ],
                [
                    'name' => 'Fruit Skewer', 'slug' => 'fruit-skewer',
                    'desc' => 'Tusuk buah warna-warni segar, cocok untuk acara.',
                    'price' => 15000, 'sort' => 5,
                    'variants' => [
                        ['Per Tusuk',    'per-tusuk',    0,     1],
                        ['Isi 5 Tusuk',  'isi-5-tusuk',  60000,  2],
                    ],
                ],
                [
                    'name' => 'Rujak Manis', 'slug' => 'rujak-manis',
                    'desc' => 'Rujak buah segar dengan saus gula merah kacang yang legit.',
                    'price' => 20000, 'sort' => 6, 'variants' => [],
                ],
                [
                    'name' => 'Rujak Serut', 'slug' => 'rujak-serut',
                    'desc' => 'Rujak serut buah segar pedas manis asam, sensasi klasik.',
                    'price' => 22000, 'sort' => 7, 'variants' => [],
                ],
                [
                    'name' => 'Asinan Buah', 'slug' => 'asinan-buah',
                    'desc' => 'Asinan buah Betawi asam segar dengan kuah cuka dan cabai.',
                    'price' => 20000, 'sort' => 8, 'variants' => [],
                ],
                [
                    'name' => 'Fruit Cup Regular', 'slug' => 'fruit-cup-regular',
                    'desc' => 'Cup buah potong 3 jenis pilihan, segar untuk camilan.',
                    'price' => 18000, 'sort' => 9, 'variants' => [],
                ],
                [
                    'name' => 'Fruit Cup Premium', 'slug' => 'fruit-cup-premium',
                    'desc' => 'Cup buah potong premium 5 jenis termasuk buah impor.',
                    'price' => 28000, 'sort' => 10, 'variants' => [],
                ],
            ],
        ];
    }

    // ── USD Price Map ────────────────────────────────────────────

    private static function getUsdPrices(): array
    {
        return [
            // Buah Segar Lokal
            'mangga-harum-manis'     => 2.50,
            'mangga-gedong-gincu'    => 3.50,
            'pisang-cavendish'       => 1.75,
            'pisang-ambon'           => 1.50,
            'semangka-merah'         => 1.50,
            'melon-kuning'           => 2.00,
            'pepaya-california'      => 1.75,
            'rambutan'               => 1.50,
            'salak-pondoh'           => 1.25,
            'jeruk-siam'             => 2.00,
            'jeruk-bali'             => 3.25,
            'nanas-madu'             => 2.00,
            'belimbing-manis'        => 1.25,
            'jambu-air-merah'        => 1.25,
            'alpukat-mentega'        => 2.00,
            // Buah Impor
            'apel-fuji'              => 3.50,
            'apel-granny-smith'      => 3.50,
            'anggur-red-globe'       => 4.50,
            'anggur-shine-muscat'    => 8.50,
            'pir-yali'               => 3.00,
            'stroberi'               => 3.50,
            'blueberry'              => 4.75,
            'ceri'                   => 5.50,
            'kiwi'                   => 2.50,
            'lemon'                  => 1.50,
            'alpukat-hass'           => 3.50,
            'buah-naga-merah'        => 2.50,
            // Jus Segar
            'jus-mangga'             => 2.25,
            'jus-jeruk-segar'        => 2.25,
            'jus-semangka'           => 1.75,
            'jus-melon'              => 1.75,
            'jus-jambu'              => 2.00,
            'jus-pepaya'             => 1.75,
            'jus-alpukat'            => 2.75,
            'jus-tomat'              => 1.75,
            'jus-nanas'              => 2.00,
            'jus-belimbing'          => 2.00,
            'jus-apel-hijau'         => 2.50,
            'jus-anggur-merah'       => 2.75,
            // Smoothie & Bowl
            'smoothie-mangga-pisang' => 3.25,
            'smoothie-stroberi'      => 3.75,
            'smoothie-alpukat-cokelat'=> 4.00,
            'smoothie-blueberry'     => 4.50,
            'smoothie-semangka-mint' => 3.25,
            'tropical-smoothie'      => 3.75,
            'green-smoothie'         => 3.50,
            'acai-bowl'              => 6.50,
            'smoothie-bowl-mangga'   => 5.75,
            'smoothie-bowl-pitaya'   => 6.25,
            'smoothie-bowl-blueberry'=> 6.00,
            // Salad & Platter
            'salad-buah-segar'       => 3.00,
            'salad-buah-yogurt'      => 3.50,
            'salad-buah-keju'        => 3.25,
            'tropical-fruit-platter' => 10.00,
            'fruit-skewer'           => 1.75,
            'rujak-manis'            => 2.50,
            'rujak-serut'            => 2.75,
            'asinan-buah'            => 2.50,
            'fruit-cup-regular'      => 2.25,
            'fruit-cup-premium'      => 3.25,
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
