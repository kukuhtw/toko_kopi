<?php

require_once __DIR__ . '/FruitTemplatePlugin.php';

return [
    'class'       => FruitTemplatePlugin::class,
    'name'        => 'Fruit Store Template',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Reset semua data produk & order, lalu seed 60 menu toko buah lengkap dengan varian.',
    'requires'    => '1.0.0',
];
