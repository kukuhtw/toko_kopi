<?php

declare(strict_types=1);

require_once __DIR__ . '/MessageBirdWhatsAppChannel.php';
require_once __DIR__ . '/MessageBirdWhatsAppPlugin.php';

return [
    'class'       => MessageBirdWhatsAppPlugin::class,
    'name'        => 'MessageBird WhatsApp Gateway',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Plugin channel WhatsApp via MessageBird tanpa bergantung pada core legacy WhatsApp provider.',
    'requires'    => '1.0.0',
];
