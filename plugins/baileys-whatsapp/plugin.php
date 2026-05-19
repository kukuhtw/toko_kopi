<?php

declare(strict_types=1);

require_once __DIR__ . '/BaileysWhatsAppChannel.php';
require_once __DIR__ . '/BaileysWhatsAppPlugin.php';

return [
    'class'       => BaileysWhatsAppPlugin::class,
    'name'        => 'Baileys WhatsApp Bridge',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Plugin channel WhatsApp via bridge Node.js Baileys tanpa bergantung pada core legacy WhatsApp provider.',
    'requires'    => '1.0.0',
];
