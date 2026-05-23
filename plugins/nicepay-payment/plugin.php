<?php

require_once __DIR__ . '/NicepayClient.php';
require_once __DIR__ . '/NicepayPaymentPlugin.php';

return [
    'class' => NicepayPaymentPlugin::class,
    'name' => 'Nicepay Payment',
    'version' => '1.0.0',
    'author' => 'Codex',
    'description' => 'Scaffold integrasi Nicepay untuk membuat link pembayaran otomatis setelah order dibuat.',
    'requires' => '1.0.0',
];
