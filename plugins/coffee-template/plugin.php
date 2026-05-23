<?php

require_once __DIR__ . '/CoffeeTemplatePlugin.php';

return [
    'class'       => CoffeeTemplatePlugin::class,
    'name'        => 'Coffee Shop Template',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Reset semua data produk & order, lalu seed 132 menu kopi lengkap (kopi panas, kopi dingin, non-kopi, cemilan, paket hemat, makanan utama, dessert) dari data asli toko.',
    'requires'    => '1.0.0',
];
