<?php

require_once __DIR__ . '/XenditClient.php';
require_once __DIR__ . '/XenditPaymentPlugin.php';

return [
    'class' => XenditPaymentPlugin::class,
    'name' => 'Xendit Payment',
    'version' => '1.0.0',
    'author' => 'Codex',
    'description' => 'Integrasi Xendit Payment Link/Invoice untuk membuat link pembayaran otomatis setelah order dibuat.',
    'requires' => '1.0.0',
];
