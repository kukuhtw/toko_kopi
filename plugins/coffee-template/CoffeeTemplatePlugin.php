<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class CoffeeTemplatePlugin implements PluginInterface
{
    public function getName(): string    { return 'Coffee Shop Template'; }
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
                'url'   => '/dashboard/super/coffee-template.php',
                'icon'  => '☕',
                'label' => 'Coffee Template',
            ];
        }

        return $items;
    }

    // ── Seed Data ────────────────────────────────────────────────

    /** Each entry: [name, slug, description, sort_order] */
    public static function getCategories(): array
    {
        return [
            ['Kopi Panas',        'kopi-panas',        'Minuman kopi panas pilihan barista kami',            1],
            ['Kopi Dingin',       'kopi-dingin',        'Minuman kopi dingin menyegarkan',                    2],
            ['Non Kopi',          'non-kopi',           'Pilihan minuman non-kopi yang beragam',              3],
            ['Cemilan',           'cemilan',            'Makanan ringan dan kue untuk teman ngopi',           4],
            ['Paket Hemat',       'paket-hemat',        'Paket kombinasi hemat dan menguntungkan',            5],
            ['Makanan Utama',     'makanan-utama',      'Hidangan utama untuk sarapan dan makan siang',       6],
            ['Yoghurt & Dessert', 'yoghurt-dessert',    'Yoghurt beku dan dessert menyegarkan',               7],
        ];
    }

    /**
     * Each item: [name, slug, desc, price (IDR), sort, variants[]]
     * Each variant: [label, slug, idr_delta, sort]
     */
    public static function getMenuItems(): array
    {
        return [

            // ── Kopi Panas (28 items) ──────────────────────────────
            'kopi-panas' => [
                ['name'=>'Espresso',          'slug'=>'espresso',          'desc'=>'Single shot espresso murni, bold & concentrated',                                                 'price'=>18000, 'sort'=>1,  'variants'=>[]],
                ['name'=>'Americano',         'slug'=>'americano',         'desc'=>'Espresso dengan air panas, smooth & rich',                                                        'price'=>20000, 'sort'=>2,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',10000,3]]],
                ['name'=>'Cappuccino',        'slug'=>'cappuccino',        'desc'=>'Espresso, steamed milk & milk foam yang sempurna',                                                 'price'=>25000, 'sort'=>3,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',10000,3]]],
                ['name'=>'Latte',             'slug'=>'latte',             'desc'=>'Espresso dengan banyak steamed milk, creamy & mild',                                              'price'=>27000, 'sort'=>4,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',10000,3]]],
                ['name'=>'Flat White',        'slug'=>'flat-white',        'desc'=>'Double ristretto dengan textured milk, smooth & strong',                                           'price'=>28000, 'sort'=>5,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',10000,3]]],
                ['name'=>'Macchiato',         'slug'=>'macchiato',         'desc'=>'Espresso dengan sedikit milk foam di atasnya',                                                    'price'=>22000, 'sort'=>6,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',5000,3]]],
                ['name'=>'Kopi Tubruk',       'slug'=>'kopi-tubruk',       'desc'=>'Kopi tradisional Indonesia yang kental',                                                          'price'=>15000, 'sort'=>7,  'variants'=>[]],
                ['name'=>'Kopi Susu Jahe',    'slug'=>'kopi-susu-jahe',    'desc'=>'Kopi susu dengan rempah jahe hangat',                                                             'price'=>22000, 'sort'=>8,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',5000,3]]],
                ['name'=>'Cortado',           'slug'=>'cortado',           'desc'=>'Espresso dengan equal parts steamed milk, balance sempurna antara kopi & susu',                   'price'=>23000, 'sort'=>9,  'variants'=>[]],
                ['name'=>'Lungo',             'slug'=>'lungo',             'desc'=>'Espresso dengan volume air lebih banyak, milder & longer finish',                                 'price'=>21000, 'sort'=>10, 'variants'=>[]],
                ['name'=>'Ristretto',         'slug'=>'ristretto',         'desc'=>'Espresso shot pendek yang sangat pekat & concentrated, pure intensity',                           'price'=>20000, 'sort'=>11, 'variants'=>[]],
                ['name'=>'Affogato',          'slug'=>'affogato',          'desc'=>'Espresso panas dituangkan langsung di atas satu scoop vanilla ice cream',                         'price'=>32000, 'sort'=>12, 'variants'=>[]],
                ['name'=>'Irish Coffee',      'slug'=>'irish-coffee',      'desc'=>'Kopi panas dengan sirup karamel Irish & cream di atasnya, rich & warming',                       'price'=>35000, 'sort'=>13, 'variants'=>[]],
                ['name'=>'Turkish Coffee',    'slug'=>'turkish-coffee',    'desc'=>'Kopi halus khas Turki diseduh dengan cezve, kental, aromatik & penuh karakter',                  'price'=>22000, 'sort'=>14, 'variants'=>[]],
                ['name'=>'Kopi Gula Aren',    'slug'=>'kopi-gula-aren',    'desc'=>'Espresso dengan gula aren lokal asli, manis legit khas Nusantara',                               'price'=>22000, 'sort'=>15, 'variants'=>[]],
                ['name'=>'Kopi Jahe Rempah',  'slug'=>'kopi-jahe-rempah',  'desc'=>'Espresso dengan jahe segar, kayu manis & cengkeh — hangat & menyehatkan',                       'price'=>20000, 'sort'=>16, 'variants'=>[]],
                ['name'=>'Kopi Susu Aren',    'slug'=>'kopi-susu-aren',    'desc'=>'Espresso susu dengan gula aren pilihan, lebih autentik dari gula biasa',                         'price'=>25000, 'sort'=>17, 'variants'=>[]],
                ['name'=>'Hazelnut Latte',    'slug'=>'hazelnut-latte',    'desc'=>'Latte creamy dengan sirup hazelnut premium, nutty & smooth',                                     'price'=>30000, 'sort'=>18, 'variants'=>[]],
                ['name'=>'Vanilla Latte',     'slug'=>'vanilla-latte',     'desc'=>'Latte dengan vanilla bean asli, smooth & fragrant setiap tegukan',                               'price'=>28000, 'sort'=>19, 'variants'=>[]],
                ['name'=>'Caramel Latte',     'slug'=>'caramel-latte',     'desc'=>'Latte dengan drizzle karamel premium di atas foam susu, manis & creamy',                        'price'=>29000, 'sort'=>20, 'variants'=>[]],
                ['name'=>'Lavender Latte',    'slug'=>'lavender-latte',    'desc'=>'Latte dengan sirup lavender, aroma floral yang calming & unik',                                  'price'=>30000, 'sort'=>21, 'variants'=>[]],
                ['name'=>'Ube Latte',         'slug'=>'ube-latte',         'desc'=>'Latte dengan ube (keladi ungu) Filipina, creamy, manis alami & berwarna cantik',                 'price'=>32000, 'sort'=>22, 'variants'=>[]],
                ['name'=>'Pandan Latte',      'slug'=>'pandan-latte',      'desc'=>'Latte dengan pandan lokal, aroma wangi khas Indonesia yang otentik',                             'price'=>27000, 'sort'=>23, 'variants'=>[]],
                ['name'=>'Brown Sugar Latte', 'slug'=>'brown-sugar-latte', 'desc'=>'Latte dengan brown sugar syrup & kayu manis, manis alami nan elegan',                            'price'=>28000, 'sort'=>24, 'variants'=>[]],
                ['name'=>'Bulletproof Coffee','slug'=>'bulletproof-coffee','desc'=>'Kopi blended dengan butter grass-fed & MCT oil, energi tahan lama keto-friendly',               'price'=>35000, 'sort'=>25, 'variants'=>[]],
                ['name'=>'Piccolo Latte',     'slug'=>'piccolo-latte',     'desc'=>'Mini latte 90ml — double ristretto dengan sedikit textured milk, bold & strong',                 'price'=>24000, 'sort'=>26, 'variants'=>[]],
                ['name'=>'Kopi Mentega Madu', 'slug'=>'kopi-mentega-madu', 'desc'=>'Kopi dengan butter lokal & madu hutan, creamy & naturally sweet',                               'price'=>28000, 'sort'=>27, 'variants'=>[]],
                ['name'=>'Gibraltar',         'slug'=>'gibraltar',         'desc'=>'Double ristretto dengan steamed whole milk dalam gelas kecil 4oz, intense & creamy',             'price'=>25000, 'sort'=>28, 'variants'=>[]],
            ],

            // ── Kopi Dingin (27 items) ─────────────────────────────
            'kopi-dingin' => [
                ['name'=>'Iced Americano',        'slug'=>'iced-americano',        'desc'=>'Americano segar di atas es batu',                                                                    'price'=>22000, 'sort'=>1,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',5000,3]]],
                ['name'=>'Iced Latte',            'slug'=>'iced-latte',            'desc'=>'Latte creamy yang menyegarkan dengan es',                                                            'price'=>29000, 'sort'=>2,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',10000,3]]],
                ['name'=>'Cold Brew',             'slug'=>'cold-brew',             'desc'=>'Kopi diseduh dingin 12 jam, smooth & less bitter',                                                   'price'=>32000, 'sort'=>3,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',10000,3]]],
                ['name'=>'Es Kopi Susu',          'slug'=>'es-kopi-susu',          'desc'=>'Kopi susu khas Indonesia yang viral',                                                                'price'=>25000, 'sort'=>4,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',5000,3]]],
                ['name'=>'Vietnamese Coffee',     'slug'=>'vietnamese-coffee',     'desc'=>'Kopi kental dengan susu kental manis',                                                              'price'=>27000, 'sort'=>5,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',5000,3]]],
                ['name'=>'Caramel Frappe',        'slug'=>'caramel-frappe',        'desc'=>'Blended coffee dengan karamel, creamy & manis',                                                     'price'=>33000, 'sort'=>6,  'variants'=>[['Regular','regular',0,1],['Large','large',5000,2]]],
                ['name'=>'Mocha Frappe',          'slug'=>'mocha-frappe',          'desc'=>'Blended coffee dengan cokelat dan whipped cream',                                                   'price'=>33000, 'sort'=>7,  'variants'=>[['Regular','regular',0,1],['Large','large',5000,2]]],
                ['name'=>'Iced Cappuccino',       'slug'=>'iced-cappuccino',       'desc'=>'Cappuccino segar disajikan dingin dengan es batu yang berlimpah',                                   'price'=>27000, 'sort'=>8,  'variants'=>[]],
                ['name'=>'Iced Flat White',       'slug'=>'iced-flat-white',       'desc'=>'Flat white double shot dengan susu full cream dingin & es batu',                                    'price'=>30000, 'sort'=>9,  'variants'=>[]],
                ['name'=>'Iced Macchiato',        'slug'=>'iced-macchiato',        'desc'=>'Macchiato es berlapis, susu di bawah lalu espresso perlahan di atasnya',                           'price'=>25000, 'sort'=>10, 'variants'=>[]],
                ['name'=>'Nitro Cold Brew',       'slug'=>'nitro-cold-brew',       'desc'=>'Cold brew dengan nitrogen — tekstur creamy tanpa susu, extremely smooth',                          'price'=>38000, 'sort'=>11, 'variants'=>[]],
                ['name'=>'Cold Brew Tonic',       'slug'=>'cold-brew-tonic',       'desc'=>'Cold brew dicampur tonic water berbuih, segar dengan citrus notes',                               'price'=>35000, 'sort'=>12, 'variants'=>[]],
                ['name'=>'Iced Hazelnut Latte',   'slug'=>'iced-hazelnut-latte',   'desc'=>'Latte hazelnut dingin, nutty & manis, sempurna untuk hari panas',                                  'price'=>32000, 'sort'=>13, 'variants'=>[]],
                ['name'=>'Iced Vanilla Latte',    'slug'=>'iced-vanilla-latte',    'desc'=>'Latte vanilla segar dengan es, fragrant & smooth di setiap tegukan',                              'price'=>30000, 'sort'=>14, 'variants'=>[]],
                ['name'=>'Iced Caramel Latte',    'slug'=>'iced-caramel-latte',    'desc'=>'Latte karamel dingin dengan drizzle karamel di atas, manis & indulgent',                          'price'=>31000, 'sort'=>15, 'variants'=>[]],
                ['name'=>'Iced Brown Sugar Latte','slug'=>'iced-brown-sugar-latte','desc'=>'Brown sugar latte dingin dengan cinnamon stick, manis alami & syrupy',                            'price'=>30000, 'sort'=>16, 'variants'=>[]],
                ['name'=>'Iced Pandan Latte',     'slug'=>'iced-pandan-latte',     'desc'=>'Latte pandan segar, wangi khas & menyegarkan, green & tropical',                                  'price'=>29000, 'sort'=>17, 'variants'=>[]],
                ['name'=>'Iced Ube Latte',        'slug'=>'iced-ube-latte',        'desc'=>'Ube latte ungu cantik disajikan dengan es, creamy & Instagrammable',                              'price'=>34000, 'sort'=>18, 'variants'=>[]],
                ['name'=>'Iced Lavender Latte',   'slug'=>'iced-lavender-latte',   'desc'=>'Lavender latte floral yang menyegarkan, perfect summer drink',                                    'price'=>32000, 'sort'=>19, 'variants'=>[]],
                ['name'=>'Es Kopi Gula Aren',     'slug'=>'es-kopi-gula-aren',     'desc'=>'Kopi gula aren asli disajikan dingin, manis legit khas Nusantara',                               'price'=>27000, 'sort'=>20, 'variants'=>[]],
                ['name'=>'Iced Cortado',          'slug'=>'iced-cortado',          'desc'=>'Cortado segar dengan es batu, balance antara espresso & susu',                                    'price'=>25000, 'sort'=>21, 'variants'=>[]],
                ['name'=>'Coffee Tonic',          'slug'=>'coffee-tonic',          'desc'=>'Espresso & tonic water berbuih — kombinasi tak terduga yang addictive',                           'price'=>33000, 'sort'=>22, 'variants'=>[]],
                ['name'=>'Iced Dirty Matcha',     'slug'=>'iced-dirty-matcha',     'desc'=>'Matcha latte dengan espresso shot di atasnya, double kick caffeine',                              'price'=>35000, 'sort'=>23, 'variants'=>[]],
                ['name'=>'Dalgona Coffee',        'slug'=>'dalgona-coffee',        'desc'=>'Whipped coffee Korean style yang fluffy di atas susu dingin',                                     'price'=>30000, 'sort'=>24, 'variants'=>[]],
                ['name'=>'Iced Spanish Latte',    'slug'=>'iced-spanish-latte',    'desc'=>'Espresso dengan susu kental manis & susu segar, manis & very creamy',                            'price'=>31000, 'sort'=>25, 'variants'=>[]],
                ['name'=>'Iced Coconut Latte',    'slug'=>'iced-coconut-latte',    'desc'=>'Latte dengan coconut milk, tropical, dairy-free & naturally sweet',                              'price'=>32000, 'sort'=>26, 'variants'=>[]],
                ['name'=>'Es Kopi Susu Aren',     'slug'=>'es-kopi-susu-aren',     'desc'=>'Es kopi susu dengan gula aren murni — upgrade dari versi gula biasa',                            'price'=>28000, 'sort'=>27, 'variants'=>[]],
            ],

            // ── Non Kopi (25 items) ────────────────────────────────
            'non-kopi' => [
                ['name'=>'Matcha Latte',       'slug'=>'matcha-latte',       'desc'=>'Green tea Jepang dengan steamed milk, creamy & earthy',                                          'price'=>28000, 'sort'=>1,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',10000,3]]],
                ['name'=>'Chocolate',          'slug'=>'chocolate',          'desc'=>'Rich hot chocolate yang mewah',                                                                   'price'=>25000, 'sort'=>2,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',10000,3]]],
                ['name'=>'Teh Tarik',          'slug'=>'teh-tarik',          'desc'=>'Teh Malaysia classic yang creamy',                                                                'price'=>20000, 'sort'=>3,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',5000,3]]],
                ['name'=>'Strawberry Smoothie','slug'=>'strawberry-smoothie','desc'=>'Smoothie stroberi segar dengan yogurt',                                                          'price'=>30000, 'sort'=>4,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',10000,3]]],
                ['name'=>'Lemon Tea',          'slug'=>'lemon-tea',          'desc'=>'Teh lemon segar, cocok untuk cuaca panas',                                                        'price'=>18000, 'sort'=>5,  'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',5000,3]]],
                ['name'=>'Iced Matcha Latte',  'slug'=>'iced-matcha-latte',  'desc'=>'Matcha latte Jepang disajikan dingin, earthy & refreshing',                                      'price'=>30000, 'sort'=>6,  'variants'=>[]],
                ['name'=>'Matcha Frappe',      'slug'=>'matcha-frappe',      'desc'=>'Blended matcha dengan milk & es batu, thick, creamy & intense',                                  'price'=>32000, 'sort'=>7,  'variants'=>[]],
                ['name'=>'Hojicha Latte',      'slug'=>'hojicha-latte',      'desc'=>'Teh hijau panggang Jepang, roasty & nutty, less caffeine & very comforting',                    'price'=>28000, 'sort'=>8,  'variants'=>[]],
                ['name'=>'Iced Hojicha Latte', 'slug'=>'iced-hojicha-latte', 'desc'=>'Hojicha latte segar disajikan dingin, roasty & slightly sweet',                                 'price'=>29000, 'sort'=>9,  'variants'=>[]],
                ['name'=>'Taro Latte',         'slug'=>'taro-latte',         'desc'=>'Latte dengan keladi ungu Filipina, creamy, manis alami & berwarna cantik',                       'price'=>30000, 'sort'=>10, 'variants'=>[]],
                ['name'=>'Iced Taro Latte',    'slug'=>'iced-taro-latte',    'desc'=>'Taro latte ungu cantik disajikan dingin, smooth & tropical',                                    'price'=>31000, 'sort'=>11, 'variants'=>[]],
                ['name'=>'Butterfly Pea Latte','slug'=>'butterfly-pea-latte','desc'=>'Latte dengan bunga telang biru, warna indah & unik — berubah ungu dengan lemon',               'price'=>28000, 'sort'=>12, 'variants'=>[]],
                ['name'=>'Iced Chocolate',     'slug'=>'iced-chocolate',     'desc'=>'Rich dark chocolate milk dingin dengan es batu, indulgent & refreshing',                        'price'=>27000, 'sort'=>13, 'variants'=>[]],
                ['name'=>'Chocolate Frappe',   'slug'=>'chocolate-frappe',   'desc'=>'Blended chocolate dengan whipped cream & chocolate drizzle, pure indulgence',                   'price'=>33000, 'sort'=>14, 'variants'=>[]],
                ['name'=>'Milo Dinosaur',      'slug'=>'milo-dinosaur',      'desc'=>'Milo iced dengan taburan Milo bubuk yang banyak di atasnya — legendary',                       'price'=>25000, 'sort'=>15, 'variants'=>[]],
                ['name'=>'Avocado Smoothie',   'slug'=>'avocado-smoothie',   'desc'=>'Smoothie alpukat segar dengan susu full cream & sedikit madu, creamy & healthy',               'price'=>32000, 'sort'=>16, 'variants'=>[]],
                ['name'=>'Mango Smoothie',     'slug'=>'mango-smoothie',     'desc'=>'Smoothie mangga harum & segar dari buah mangga asli, tropical vibes',                          'price'=>30000, 'sort'=>17, 'variants'=>[]],
                ['name'=>'Chai Latte',         'slug'=>'chai-latte',         'desc'=>'Teh masala India dengan kayu manis, jahe, kapulaga & steamed milk, spicy warm',               'price'=>27000, 'sort'=>18, 'variants'=>[]],
                ['name'=>'Iced Chai Latte',    'slug'=>'iced-chai-latte',    'desc'=>'Chai latte rempah disajikan dingin, spicy & refreshing, summer favourite',                     'price'=>28000, 'sort'=>19, 'variants'=>[]],
                ['name'=>'Pandan Susu',        'slug'=>'pandan-susu',        'desc'=>'Susu segar dengan pandan lokal — tanpa kopi, sweet, fragrant & soothing',                     'price'=>25000, 'sort'=>20, 'variants'=>[]],
                ['name'=>'Iced Thai Tea',      'slug'=>'iced-thai-tea',      'desc'=>'Teh Thailand khas dengan susu evaporasi bergula, manis & creamy',                              'price'=>25000, 'sort'=>21, 'variants'=>[]],
                ['name'=>'Banana Smoothie',    'slug'=>'banana-smoothie',    'desc'=>'Smoothie pisang creamy dengan susu & madu, natural energy booster',                            'price'=>28000, 'sort'=>22, 'variants'=>[]],
                ['name'=>'Red Velvet Latte',   'slug'=>'red-velvet-latte',   'desc'=>'Latte dengan red velvet flavour, creamy & indulgent, rich colour',                             'price'=>30000, 'sort'=>23, 'variants'=>[]],
                ['name'=>'Iced Teh Tarik',     'slug'=>'iced-teh-tarik',     'desc'=>'Teh tarik khas Malaysia disajikan dingin & segar, creamy & refreshing',                       'price'=>22000, 'sort'=>24, 'variants'=>[]],
                ['name'=>'Coconut Water Fresh','slug'=>'coconut-water-fresh','desc'=>'Air kelapa muda segar langsung dari buahnya, natural & hydrating',                             'price'=>28000, 'sort'=>25, 'variants'=>[]],
            ],

            // ── Cemilan (26 items) ─────────────────────────────────
            'cemilan' => [
                ['name'=>'Croissant',             'slug'=>'croissant',             'desc'=>'Croissant butter lembut & renyah dari oven',                                                          'price'=>22000, 'sort'=>1,  'variants'=>[]],
                ['name'=>'Banana Bread',          'slug'=>'banana-bread',          'desc'=>'Roti pisang moist dengan walnuts',                                                                    'price'=>25000, 'sort'=>2,  'variants'=>[]],
                ['name'=>'Cheese Cake Slice',     'slug'=>'cheese-cake-slice',     'desc'=>'Slice cheesecake original New York style',                                                           'price'=>32000, 'sort'=>3,  'variants'=>[]],
                ['name'=>'Cookies Assorted',      'slug'=>'cookies-assorted',      'desc'=>'Mixed cookies: chocolate chip, oatmeal, peanut butter',                                             'price'=>20000, 'sort'=>4,  'variants'=>[['Regular','regular',0,1],['Large','large',10000,2]]],
                ['name'=>'Sandwich Club',         'slug'=>'sandwich-club',         'desc'=>'Sandwich ayam, keju, selada & tomat',                                                                'price'=>35000, 'sort'=>5,  'variants'=>[]],
                ['name'=>'French Fries',          'slug'=>'french-fries',          'desc'=>'Kentang goreng crispy dengan saus',                                                                  'price'=>25000, 'sort'=>6,  'variants'=>[['Regular','regular',0,1],['Large','large',10000,2]]],
                ['name'=>'Roti Bakar Cokelat Keju','slug'=>'roti-bakar-cokelat-keju','desc'=>'Roti bakar tebal dengan lelehan cokelat & keju mozzarella, comfort food sejati',               'price'=>25000, 'sort'=>7,  'variants'=>[]],
                ['name'=>'Roti Bakar Pandan Keju','slug'=>'roti-bakar-pandan-keju','desc'=>'Roti bakar dengan selai pandan wangi & parutan keju di atasnya',                                  'price'=>23000, 'sort'=>8,  'variants'=>[]],
                ['name'=>'Waffle Original',       'slug'=>'waffle-original',       'desc'=>'Waffle crispy di luar, lembut di dalam dengan butter & maple syrup',                               'price'=>32000, 'sort'=>9,  'variants'=>[]],
                ['name'=>'Waffle Cokelat Keju',   'slug'=>'waffle-cokelat-keju',   'desc'=>'Waffle dengan topping cokelat premium & keju parut yang melimpah',                                'price'=>35000, 'sort'=>10, 'variants'=>[]],
                ['name'=>'Pancake Stack',         'slug'=>'pancake-stack',         'desc'=>'Stack 3 pancake fluffy & thick dengan maple syrup & whipped cream',                                'price'=>35000, 'sort'=>11, 'variants'=>[]],
                ['name'=>'Muffin Blueberry',      'slug'=>'muffin-blueberry',      'desc'=>'Muffin moist dengan blueberry segar yang burst di setiap gigitan',                                 'price'=>22000, 'sort'=>12, 'variants'=>[]],
                ['name'=>'Muffin Choco Chip',     'slug'=>'muffin-choco-chip',     'desc'=>'Muffin cokelat dengan chocolate chips premium yang melimpah',                                      'price'=>22000, 'sort'=>13, 'variants'=>[]],
                ['name'=>'Donat Glazur',          'slug'=>'donat-glazur',          'desc'=>'Donat lembut dengan glazur gula putih klasik, soft & pillowy',                                    'price'=>18000, 'sort'=>14, 'variants'=>[]],
                ['name'=>'Donat Cokelat Keju',    'slug'=>'donat-cokelat-keju',    'desc'=>'Donat dengan topping cokelat & taburan keju parut, sweet & savory',                               'price'=>22000, 'sort'=>15, 'variants'=>[]],
                ['name'=>'Brownies Fudgy',        'slug'=>'brownies-fudgy',        'desc'=>'Brownies fudgy ultra-dark chocolate, dense, moist & decadent',                                    'price'=>25000, 'sort'=>16, 'variants'=>[]],
                ['name'=>'Tiramisu Slice',        'slug'=>'tiramisu-slice',        'desc'=>'Slice tiramisu klasik Italia dengan lady fingers, mascarpone & espresso',                          'price'=>37000, 'sort'=>17, 'variants'=>[]],
                ['name'=>'Bolu Pandan',           'slug'=>'bolu-pandan',           'desc'=>'Bolu pandan lembut & wangi khas Nusantara, soft & fragrant',                                      'price'=>25000, 'sort'=>18, 'variants'=>[]],
                ['name'=>'Choux Cream Vanilla',   'slug'=>'choux-cream-vanilla',   'desc'=>'Choux pastry ringan dengan isian vanilla custard cream yang lembut',                               'price'=>25000, 'sort'=>19, 'variants'=>[]],
                ['name'=>'Pie Susu Bali',         'slug'=>'pie-susu-bali',         'desc'=>'Pie susu khas Bali dengan filling susu legit, thin crust & creamy',                               'price'=>20000, 'sort'=>20, 'variants'=>[]],
                ['name'=>'Roti Sobek Cheese',     'slug'=>'roti-sobek-cheese',     'desc'=>'Roti sobek lembut dengan keju mozzarella yang meleleh di setiap sobekan',                        'price'=>30000, 'sort'=>21, 'variants'=>[]],
                ['name'=>'Pretzel Klasik',        'slug'=>'pretzel-klasik',        'desc'=>'Pretzel German style dengan garam kasar, crispy luar & chewy dalam',                              'price'=>25000, 'sort'=>22, 'variants'=>[]],
                ['name'=>'Nachos Salsa',          'slug'=>'nachos-salsa',          'desc'=>'Nachos crispy dengan salsa tomat segar, sour cream & jalapeño',                                   'price'=>30000, 'sort'=>23, 'variants'=>[]],
                ['name'=>'Chicken Wrap',          'slug'=>'chicken-wrap',          'desc'=>'Wrap tortilla dengan ayam panggang, selada segar, tomat & mayo',                                  'price'=>38000, 'sort'=>24, 'variants'=>[]],
                ['name'=>'Scone Clotted Cream',   'slug'=>'scone-clotted-cream',   'desc'=>'Scone English style dengan clotted cream & strawberry jam, proper British tea',                   'price'=>28000, 'sort'=>25, 'variants'=>[]],
                ['name'=>'Bruschetta Tomat',      'slug'=>'bruschetta-tomat',      'desc'=>'Roti ciabatta panggang dengan tomat segar, basil & extra virgin olive oil',                       'price'=>32000, 'sort'=>26, 'variants'=>[]],
            ],

            // ── Paket Hemat (13 items) ─────────────────────────────
            'paket-hemat' => [
                ['name'=>'Paket Ngopi Santai',      'slug'=>'paket-ngopi-santai',      'desc'=>'1 Americano + 1 Banana Bread (hemat Rp 5.000)',                                       'price'=>40000,  'sort'=>1,  'variants'=>[]],
                ['name'=>'Paket Kerja',             'slug'=>'paket-kerja',             'desc'=>'1 Latte + 1 Croissant + free refill air (hemat Rp 7.000)',                           'price'=>47000,  'sort'=>2,  'variants'=>[]],
                ['name'=>'Paket Berdua',            'slug'=>'paket-berdua',            'desc'=>'2 Es Kopi Susu + 2 Cookies (hemat Rp 10.000)',                                       'price'=>70000,  'sort'=>3,  'variants'=>[]],
                ['name'=>'Paket Pagi Spesial',      'slug'=>'paket-pagi-spesial',      'desc'=>'1 Kopi Susu Aren + 1 Roti Bakar Cokelat Keju (hemat Rp 7.000)',                     'price'=>40000,  'sort'=>4,  'variants'=>[]],
                ['name'=>'Paket Siang Produktif',   'slug'=>'paket-siang-produktif',   'desc'=>'1 Iced Vanilla Latte + 1 Sandwich Club (hemat Rp 9.000)',                            'price'=>56000,  'sort'=>5,  'variants'=>[]],
                ['name'=>'Paket Sore Santai',       'slug'=>'paket-sore-santai',       'desc'=>'1 Cold Brew Tonic + 1 Tiramisu Slice (hemat Rp 10.000)',                            'price'=>62000,  'sort'=>6,  'variants'=>[]],
                ['name'=>'Paket Malam Relax',       'slug'=>'paket-malam-relax',       'desc'=>'1 Hojicha Latte + 1 Bolu Pandan (hemat Rp 5.000)',                                  'price'=>48000,  'sort'=>7,  'variants'=>[]],
                ['name'=>'Paket Berdua Spesial',    'slug'=>'paket-berdua-spesial',    'desc'=>'2 Es Kopi Susu Aren + 2 Waffle Original (hemat Rp 14.000)',                         'price'=>112000, 'sort'=>8,  'variants'=>[]],
                ['name'=>'Paket Keluarga Hemat',    'slug'=>'paket-keluarga-hemat',    'desc'=>'4 Iced Latte + 4 Roti Bakar Cokelat Keju (hemat Rp 24.000)',                        'price'=>192000, 'sort'=>9,  'variants'=>[]],
                ['name'=>'Paket Meeting Pro',       'slug'=>'paket-meeting-pro',       'desc'=>'5 Americano + 1 Loyang Brownies 6pcs (hemat Rp 20.000)',                            'price'=>230000, 'sort'=>10, 'variants'=>[]],
                ['name'=>'Paket Nongkrong Asik',    'slug'=>'paket-nongkrong-asik',    'desc'=>'3 Cold Brew Tonic + 3 Nachos Salsa (hemat Rp 18.000)',                              'price'=>177000, 'sort'=>11, 'variants'=>[]],
                ['name'=>'Paket Matcha Mania',      'slug'=>'paket-matcha-mania',      'desc'=>'2 Iced Matcha Latte + 2 Matcha Frappe (hemat Rp 10.000)',                           'price'=>114000, 'sort'=>12, 'variants'=>[]],
                ['name'=>'Paket Best Seller',       'slug'=>'paket-best-seller',       'desc'=>'1 Nitro Cold Brew + 1 Iced Latte + 1 Waffle Original + 1 Brownies Fudgy',          'price'=>120000, 'sort'=>13, 'variants'=>[]],
            ],

            // ── Makanan Utama (10 items) ───────────────────────────
            'makanan-utama' => [
                ['name'=>'Nasi Goreng Kopi', 'slug'=>'nasi-goreng-kopi', 'desc'=>'Nasi goreng spesial dengan infused cold brew, telur mata sapi & kerupuk udang',         'price'=>45000, 'sort'=>1,  'variants'=>[]],
                ['name'=>'Ayam Bakar Kopi',  'slug'=>'ayam-bakar-kopi',  'desc'=>'Ayam bakar dengan marinade kopi & rempah pilihan, crispy luar juicy dalam',              'price'=>55000, 'sort'=>2,  'variants'=>[]],
                ['name'=>'Pasta Carbonara',  'slug'=>'pasta-carbonara',  'desc'=>'Pasta creamy dengan telur segar, pancetta & parmesan, Italian classic recipe',           'price'=>52000, 'sort'=>3,  'variants'=>[]],
                ['name'=>'Caesar Salad',     'slug'=>'caesar-salad',     'desc'=>'Romaine lettuce segar dengan dressing Caesar, crouton & parmesan shaved',                'price'=>40000, 'sort'=>4,  'variants'=>[]],
                ['name'=>'Omurice',          'slug'=>'omurice',          'desc'=>'Nasi goreng dibungkus telur dadar mulus, saus tomat & keju di atasnya',                  'price'=>45000, 'sort'=>5,  'variants'=>[]],
                ['name'=>'Eggs Benedict',    'slug'=>'eggs-benedict',    'desc'=>'Poached eggs sempurna di atas English muffin & smoked beef dengan hollandaise',          'price'=>55000, 'sort'=>6,  'variants'=>[]],
                ['name'=>'Granola Bowl',     'slug'=>'granola-bowl',     'desc'=>'Granola homemade dengan Greek yogurt, madu bunga & buah segar seasonal',                 'price'=>38000, 'sort'=>7,  'variants'=>[]],
                ['name'=>'Acai Bowl',        'slug'=>'coffee-acai-bowl', 'desc'=>'Acai blend dengan granola renyah, pisang, kelapa parut & mixed berries',                 'price'=>48000, 'sort'=>8,  'variants'=>[]],
                ['name'=>'Focaccia Sandwich','slug'=>'focaccia-sandwich','desc'=>'Focaccia panggang dengan prosciutto, mozzarella segar, pesto & sun-dried tomato',        'price'=>52000, 'sort'=>9,  'variants'=>[]],
                ['name'=>'Quiche Lorraine',  'slug'=>'quiche-lorraine',  'desc'=>'Quiche klasik Prancis dengan bacon asap, gruyère & krim, dipanggang fresh daily',       'price'=>45000, 'sort'=>10, 'variants'=>[]],
            ],

            // ── Yoghurt & Dessert (3 items) ────────────────────────
            'yoghurt-dessert' => [
                ['name'=>'Frozen Yoghurt 2 Toppings','slug'=>'frozen-yoghurt-2-top','desc'=>'Frozen yoghurt dengan pilihan 2 topping. Opsi: Granola, Strawberry, Blueberry, Mango, Banana, Almond, Choco Chips, Oreo Crumbs.','price'=>28000,'sort'=>1,'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',10000,3]]],
                ['name'=>'Frozen Yoghurt 3 Toppings','slug'=>'frozen-yoghurt-3-top','desc'=>'Frozen yoghurt dengan pilihan 3 topping. Opsi: Granola, Strawberry, Blueberry, Mango, Banana, Almond, Choco Chips, Oreo Crumbs.','price'=>34000,'sort'=>2,'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',10000,3]]],
                ['name'=>'Frozen Yoghurt 4 Toppings','slug'=>'frozen-yoghurt-4-top','desc'=>'Frozen yoghurt dengan pilihan 4 topping. Opsi: Granola, Strawberry, Blueberry, Mango, Banana, Almond, Choco Chips, Oreo Crumbs.','price'=>40000,'sort'=>3,'variants'=>[['Small','small',0,1],['Medium','medium',5000,2],['Large','large',10000,3]]],
            ],
        ];
    }

    // ── USD Price Map ────────────────────────────────────────────

    private static function getUsdPrices(): array
    {
        return [
            // Kopi Panas
            'espresso'           => 1.50,
            'americano'          => 2.00,
            'cappuccino'         => 2.50,
            'latte'              => 2.50,
            'flat-white'         => 2.50,
            'macchiato'          => 2.00,
            'kopi-tubruk'        => 1.50,
            'kopi-susu-jahe'     => 2.00,
            'cortado'            => 2.00,
            'lungo'              => 2.00,
            'ristretto'          => 2.00,
            'affogato'           => 3.00,
            'irish-coffee'       => 3.00,
            'turkish-coffee'     => 2.00,
            'kopi-gula-aren'     => 2.00,
            'kopi-jahe-rempah'   => 2.00,
            'kopi-susu-aren'     => 2.50,
            'hazelnut-latte'     => 2.50,
            'vanilla-latte'      => 2.50,
            'caramel-latte'      => 2.50,
            'lavender-latte'     => 2.50,
            'ube-latte'          => 3.00,
            'pandan-latte'       => 2.50,
            'brown-sugar-latte'  => 2.50,
            'bulletproof-coffee' => 3.00,
            'piccolo-latte'      => 2.00,
            'kopi-mentega-madu'  => 2.50,
            'gibraltar'          => 2.50,
            // Kopi Dingin
            'iced-americano'         => 2.00,
            'iced-latte'             => 2.50,
            'cold-brew'              => 3.00,
            'es-kopi-susu'           => 2.50,
            'vietnamese-coffee'      => 2.50,
            'caramel-frappe'         => 3.00,
            'mocha-frappe'           => 3.00,
            'iced-cappuccino'        => 2.50,
            'iced-flat-white'        => 2.50,
            'iced-macchiato'         => 2.50,
            'nitro-cold-brew'        => 3.50,
            'cold-brew-tonic'        => 3.00,
            'iced-hazelnut-latte'    => 3.00,
            'iced-vanilla-latte'     => 2.50,
            'iced-caramel-latte'     => 3.00,
            'iced-brown-sugar-latte' => 2.50,
            'iced-pandan-latte'      => 2.50,
            'iced-ube-latte'         => 3.00,
            'iced-lavender-latte'    => 3.00,
            'es-kopi-gula-aren'      => 2.50,
            'iced-cortado'           => 2.50,
            'coffee-tonic'           => 3.00,
            'iced-dirty-matcha'      => 3.00,
            'dalgona-coffee'         => 2.50,
            'iced-spanish-latte'     => 3.00,
            'iced-coconut-latte'     => 3.00,
            'es-kopi-susu-aren'      => 2.50,
            // Non Kopi
            'matcha-latte'       => 2.50,
            'chocolate'          => 2.50,
            'teh-tarik'          => 2.00,
            'strawberry-smoothie'=> 2.50,
            'lemon-tea'          => 1.50,
            'iced-matcha-latte'  => 2.50,
            'matcha-frappe'      => 3.00,
            'hojicha-latte'      => 2.50,
            'iced-hojicha-latte' => 2.50,
            'taro-latte'         => 2.50,
            'iced-taro-latte'    => 3.00,
            'butterfly-pea-latte'=> 2.50,
            'iced-chocolate'     => 2.50,
            'chocolate-frappe'   => 3.00,
            'milo-dinosaur'      => 2.50,
            'avocado-smoothie'   => 3.00,
            'mango-smoothie'     => 2.50,
            'chai-latte'         => 2.50,
            'iced-chai-latte'    => 2.50,
            'pandan-susu'        => 2.50,
            'iced-thai-tea'      => 2.50,
            'banana-smoothie'    => 2.50,
            'red-velvet-latte'   => 2.50,
            'iced-teh-tarik'     => 2.00,
            'coconut-water-fresh'=> 2.50,
            // Cemilan
            'croissant'              => 2.00,
            'banana-bread'           => 2.50,
            'cheese-cake-slice'      => 3.00,
            'cookies-assorted'       => 2.00,
            'sandwich-club'          => 3.00,
            'french-fries'           => 2.50,
            'roti-bakar-cokelat-keju'=> 2.50,
            'roti-bakar-pandan-keju' => 2.00,
            'waffle-original'        => 3.00,
            'waffle-cokelat-keju'    => 3.00,
            'pancake-stack'          => 3.00,
            'muffin-blueberry'       => 2.00,
            'muffin-choco-chip'      => 2.00,
            'donat-glazur'           => 1.50,
            'donat-cokelat-keju'     => 2.00,
            'brownies-fudgy'         => 2.50,
            'tiramisu-slice'         => 3.50,
            'bolu-pandan'            => 2.50,
            'choux-cream-vanilla'    => 2.50,
            'pie-susu-bali'          => 2.00,
            'roti-sobek-cheese'      => 2.50,
            'pretzel-klasik'         => 2.50,
            'nachos-salsa'           => 2.50,
            'chicken-wrap'           => 3.50,
            'scone-clotted-cream'    => 2.50,
            'bruschetta-tomat'       => 3.00,
            // Paket Hemat
            'paket-ngopi-santai'   => 3.50,
            'paket-kerja'          => 4.00,
            'paket-berdua'         => 6.00,
            'paket-pagi-spesial'   => 3.50,
            'paket-siang-produktif'=> 5.00,
            'paket-sore-santai'    => 5.50,
            'paket-malam-relax'    => 4.00,
            'paket-berdua-spesial' => 9.50,
            'paket-keluarga-hemat' => 16.00,
            'paket-meeting-pro'    => 19.50,
            'paket-nongkrong-asik' => 15.00,
            'paket-matcha-mania'   => 9.50,
            'paket-best-seller'    => 10.00,
            // Makanan Utama
            'nasi-goreng-kopi' => 4.00,
            'ayam-bakar-kopi'  => 5.00,
            'pasta-carbonara'  => 4.50,
            'caesar-salad'     => 3.50,
            'omurice'          => 4.00,
            'eggs-benedict'    => 5.00,
            'granola-bowl'     => 3.50,
            'coffee-acai-bowl' => 4.00,
            'focaccia-sandwich'=> 4.50,
            'quiche-lorraine'  => 4.00,
            // Yoghurt & Dessert
            'frozen-yoghurt-2-top' => 2.50,
            'frozen-yoghurt-3-top' => 3.00,
            'frozen-yoghurt-4-top' => 3.50,
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
                    $usdBase = (float)($usdPrices[$item['slug']] ?? round($idrBase / 15000, 2));

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
