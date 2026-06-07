<?php

declare(strict_types=1);

require_once __DIR__ . '/MetaWhatsAppChannel.php';
require_once __DIR__ . '/MetaWhatsAppPlugin.php';

return [
    'class' => MetaWhatsAppPlugin::class,
    'name' => 'Meta WhatsApp Gateway',
    'version' => '1.0.0',
    'author' => 'Toko Kopi',
    'description' => 'Plugin channel WhatsApp via Meta Cloud API dengan fallback migrasi dari konfigurasi legacy.',
    'requires' => '1.0.0',
];
