<?php

declare(strict_types=1);

require_once __DIR__ . '/FonnteWhatsAppChannel.php';
require_once __DIR__ . '/FonnteWhatsAppPlugin.php';

return [
    'class'       => FonnteWhatsAppPlugin::class,
    'name'        => 'Fonnte WhatsApp Gateway',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Plugin channel WhatsApp via Fonnte tanpa bergantung pada core legacy WhatsApp provider.',
    'requires'    => '1.0.0',
];
