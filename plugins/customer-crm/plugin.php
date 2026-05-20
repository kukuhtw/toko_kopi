<?php

declare(strict_types=1);

require_once __DIR__ . '/CustomerCrmPlugin.php';

return [
    'class' => CustomerCrmPlugin::class,
    'name' => 'Customer CRM',
    'version' => '1.0.0',
    'author' => 'KopiBot Team',
    'description' => 'Customer relationship management untuk normalisasi email/WhatsApp, identitas berbasis country code, dan notifikasi loyalty ke customer.',
    'requires' => '1.0.0',
];
