<?php

require_once __DIR__ . '/RestoBasoTemplatePlugin.php';

return [
    'class'       => RestoBasoTemplatePlugin::class,
    'name'        => 'Resto Baso & Minuman Template',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Reset semua data produk & order, lalu seed 15 menu resto baso: aneka bakso, mie, camilan, dan minuman segar.',
    'requires'    => '1.0.0',
];
