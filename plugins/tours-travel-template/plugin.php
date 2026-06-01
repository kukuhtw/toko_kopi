<?php

require_once __DIR__ . '/ToursTravelTemplatePlugin.php';

return [
    'class'       => ToursTravelTemplatePlugin::class,
    'name'        => 'Tours & Travel Template',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Reset semua data produk & order, lalu seed 15 layanan wisata, tur domestik, tur internasional, dan add-on travel.',
    'requires'    => '1.0.0',
];
