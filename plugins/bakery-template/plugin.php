<?php

require_once __DIR__ . '/BakeryTemplatePlugin.php';

return [
    'class'       => BakeryTemplatePlugin::class,
    'name'        => 'Bakery Template',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Reset semua data produk & order, lalu seed 70 menu toko roti bakery lengkap dengan varian.',
    'requires'    => '1.0.0',
];
