<?php

require_once __DIR__ . '/PharmacyTemplatePlugin.php';

return [
    'class'       => PharmacyTemplatePlugin::class,
    'name'        => 'Pharmacy / Apotek Template',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Reset semua data produk & order, lalu seed 120 produk apotek lengkap dengan kategori obat, vitamin, alat kesehatan, varian kemasan, dan harga IDR.',
    'requires'    => '1.0.0',
];
