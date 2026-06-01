<?php

require_once __DIR__ . '/WarungTemplatePlugin.php';

return [
    'class'       => WarungTemplatePlugin::class,
    'name'        => 'Warung Makan Template',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Reset semua data produk & order, lalu seed 15 menu warung makan khas Indonesia: nasi, lauk pauk, dan minuman warung.',
    'requires'    => '1.0.0',
];
