<?php

declare(strict_types=1);

require_once __DIR__ . '/RekomendasiPromoPlugin.php';

return [
    'class'       => RekomendasiPromoPlugin::class,
    'name'        => 'Rekomendasi Promo',
    'version'     => '1.0.0',
    'author'      => 'KopiBot',
    'description' => 'Mengingatkan customer tentang promo aktif dan kode promo saat menambah item ke keranjang.',
    'requires'    => '1.0.0',
];
