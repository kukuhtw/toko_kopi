<?php

require_once __DIR__ . '/MeatVeggieTemplatePlugin.php';

return [
    'class'       => MeatVeggieTemplatePlugin::class,
    'name'        => 'Meat & Veggie Template',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Reset semua data produk & order, lalu seed 80 menu toko daging dan sayuran lengkap dengan varian.',
    'requires'    => '1.0.0',
];
