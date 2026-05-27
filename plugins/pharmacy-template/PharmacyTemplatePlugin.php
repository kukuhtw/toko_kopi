<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};

class PharmacyTemplatePlugin implements PluginInterface
{
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

    public static function getMenuItems(): array
    {
        $categories = [];

        foreach (self::getCategories() as $category) {
            [$name, $slug] = $category;
            $categories[$slug] = [];

            for ($i = 1; $i <= 10; $i++) {
                $categories[$slug][] = [
                    'name' => $name . ' Item ' . $i,
                    'slug' => $slug . '-item-' . $i,
                    'desc' => 'Produk kategori ' . $name . ' nomor ' . $i . '.',
                    'price' => 10000 + ($i * 2500),
                    'sort' => $i,
                    'variants' => [
                        ['Strip', 'strip', 0, 1],
                        ['Box', 'box', 25000, 2],
                    ],
                ];
            }
        }

        return $categories;
    }
}
