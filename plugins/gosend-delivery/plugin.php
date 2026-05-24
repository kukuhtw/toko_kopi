<?php

declare(strict_types=1);

require_once __DIR__ . '/GoSendDeliveryRepository.php';
require_once __DIR__ . '/GoSendDeliveryClient.php';
require_once __DIR__ . '/GoSendDeliveryService.php';
require_once __DIR__ . '/GoSendDeliveryPlugin.php';

return [
    'name' => 'GoSend Delivery',
    'slug' => 'gosend-delivery',
    'class' => 'GoSendDeliveryPlugin',
    'description' => 'Scaffold integrasi GoSend API untuk booking delivery, tracking, queue, dan webhook status.',
    'version' => '1.0.0',
    'author' => 'Codex',
];
