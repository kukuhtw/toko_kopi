<?php

declare(strict_types=1);

require_once __DIR__ . '/MokaConnectRepository.php';
require_once __DIR__ . '/MokaConnectClient.php';
require_once __DIR__ . '/MokaConnectService.php';
require_once __DIR__ . '/MokaConnectPrivateSolutionPlugin.php';

return [
    'class'       => MokaConnectPrivateSolutionPlugin::class,
    'name'        => 'Moka Connect / Private Solution',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Scaffold konektor Moka Connect / Private Solution untuk sinkron outlet, katalog, customer, dan order ke Moka POS.',
    'requires'    => '1.0.0',
];
