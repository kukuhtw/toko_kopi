<?php

declare(strict_types=1);

require_once __DIR__ . '/RajaOngkirDeliveryRepository.php';
require_once __DIR__ . '/RajaOngkirDeliveryService.php';
require_once __DIR__ . '/RajaOngkirDeliveryPlugin.php';

return [
    'class'       => RajaOngkirDeliveryPlugin::class,
    'name'        => 'RajaOngkir Delivery',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Menghitung ongkir RajaOngkir untuk order delivery dan menambahkan delivery fee ke total order.',
    'requires'    => '1.0.0',
];
