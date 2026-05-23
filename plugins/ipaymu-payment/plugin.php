<?php

require_once __DIR__ . '/IPaymuClient.php';
require_once __DIR__ . '/IPaymuPaymentPlugin.php';

return [
    'class' => IPaymuPaymentPlugin::class,
    'name' => 'iPaymu Payment',
    'version' => '1.0.0',
    'author' => 'Codex',
    'description' => 'Scaffold integrasi iPaymu untuk membuat link pembayaran otomatis setelah order dibuat.',
    'requires' => '1.0.0',
];
