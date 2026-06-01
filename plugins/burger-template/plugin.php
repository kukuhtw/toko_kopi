<?php

require_once __DIR__ . '/BurgerTemplatePlugin.php';

return [
    'class'       => BurgerTemplatePlugin::class,
    'name'        => 'Burger Template',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Reset semua data produk & order, lalu seed 15 menu burger: burger beef, ayam crispy, BBQ, sides, dan minuman.',
    'requires'    => '1.0.0',
];
