<?php

declare(strict_types=1);

require_once __DIR__ . '/VonageWhatsAppChannel.php';
require_once __DIR__ . '/VonageWhatsAppPlugin.php';

return [
    'class'       => VonageWhatsAppPlugin::class,
    'name'        => 'Vonage WhatsApp Gateway',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Plugin channel WhatsApp via Vonage tanpa bergantung pada core legacy WhatsApp provider.',
    'requires'    => '1.0.0',
];
