<?php

declare(strict_types=1);

require_once __DIR__ . '/KiriminAjaDeliveryRepository.php';
require_once __DIR__ . '/KiriminAjaDeliveryClient.php';
require_once __DIR__ . '/KiriminAjaDeliveryService.php';
require_once __DIR__ . '/KiriminAjaDeliveryPlugin.php';

return [
    'name' => 'KiriminAja Delivery',
    'slug' => 'kiriminaja-delivery',
    'class' => 'KiriminAjaDeliveryPlugin',
    'description' => 'Biaya delivery per cabang dengan aktivasi oleh super admin dan operasional oleh admin cabang.',
    'version' => '1.0.0',
    'author' => 'Codex',
];
