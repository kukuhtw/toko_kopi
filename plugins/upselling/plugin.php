<?php

declare(strict_types=1);

require_once __DIR__ . '/UpsellingPlugin.php';

return [
    'class'       => UpsellingPlugin::class,
    'name'        => 'Upselling',
    'version'     => '1.0.0',
    'author'      => 'KopiBot',
    'description' => 'Menyarankan item dari kategori lain saat customer menambah item ke keranjang.',
    'requires'    => '1.0.0',
];
