<?php

require_once __DIR__ . '/KebabTemplatePlugin.php';

return [
    'class'       => KebabTemplatePlugin::class,
    'name'        => 'Kebab Template',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Reset semua data produk & order, lalu seed 15 menu kebab: kebab sapi, ayam, mozarella, shawarma, falafel, dan minuman.',
    'requires'    => '1.0.0',
];
