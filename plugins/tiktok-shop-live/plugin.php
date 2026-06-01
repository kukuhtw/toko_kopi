<?php

declare(strict_types=1);

require_once __DIR__ . '/TikTokShopLiveRepository.php';
require_once __DIR__ . '/TikTokShopLiveClient.php';
require_once __DIR__ . '/TikTokShopLiveService.php';
require_once __DIR__ . '/TikTokShopLivePlugin.php';

return [
    'class'       => TikTokShopLivePlugin::class,
    'name'        => 'TikTok Shop Live Connector',
    'version'     => '0.1.0',
    'author'      => 'Kukuh TW',
    'description' => 'Integrasi TikTok Shop Live untuk sinkron katalog, webhook order, event live, rekomendasi AI, dan analitik live commerce.',
    'requires'    => '1.0.0',
];
