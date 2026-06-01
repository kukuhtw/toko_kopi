<?php

require_once __DIR__ . '/UmrahTemplatePlugin.php';

return [
    'class'       => UmrahTemplatePlugin::class,
    'name'        => 'Umrah Template',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Reset semua data produk & order, lalu seed 15 paket umrah, plus add-on perlengkapan dan layanan dokumen.',
    'requires'    => '1.0.0',
];
