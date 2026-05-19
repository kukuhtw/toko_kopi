<?php

require_once __DIR__ . '/MidtransClient.php';
require_once __DIR__ . '/MidtransPaymentPlugin.php';

return [
    'class'       => MidtransPaymentPlugin::class,
    'name'        => 'Midtrans Payment',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Integrasi Midtrans Snap — generate link bayar otomatis setelah order dibuat.',
    'requires'    => '1.0.0',
];
