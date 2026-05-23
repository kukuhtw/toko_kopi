<?php

declare(strict_types=1);

require_once __DIR__ . '/SircloConnectorRepository.php';
require_once __DIR__ . '/SircloConnectorService.php';
require_once __DIR__ . '/SircloFullConnectorPlugin.php';

return [
    'class'       => SircloFullConnectorPlugin::class,
    'name'        => 'Sirclo Full Connector',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Fondasi integrasi SIRCLO untuk sinkronisasi order, produk, dan customer per cabang.',
    'requires'    => '1.0.0',
];
