<?php

declare(strict_types=1);

require_once __DIR__ . '/TwilioWhatsAppChannel.php';
require_once __DIR__ . '/TwilioWhatsAppPlugin.php';

return [
    'class'       => TwilioWhatsAppPlugin::class,
    'name'        => 'Twilio WhatsApp Gateway',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Plugin channel WhatsApp via Twilio tanpa bergantung pada core legacy WhatsApp provider.',
    'requires'    => '1.0.0',
];
