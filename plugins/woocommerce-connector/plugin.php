<?php

declare(strict_types=1);

require_once __DIR__ . '/WooCommerceConnectorRepository.php';
require_once __DIR__ . '/WooCommerceConnectorClient.php';
require_once __DIR__ . '/WooCommerceConnectorService.php';
require_once __DIR__ . '/WooCommerceConnectorPlugin.php';

return [
    'class'       => WooCommerceConnectorPlugin::class,
    'name'        => 'WooCommerce Connector',
    'version'     => '0.1.0',
    'author'      => 'Toko Kopi',
    'description' => 'Fondasi integrasi WooCommerce untuk sinkron katalog, push order, dan webhook status per cabang.',
    'requires'    => '1.0.0',
];
