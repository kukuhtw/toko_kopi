<?php

declare(strict_types=1);

require_once __DIR__ . '/TikTokShopLiveRepository.php';
require_once __DIR__ . '/TikTokShopLiveClient.php';
require_once __DIR__ . '/TikTokOpenApiClient.php';
require_once __DIR__ . '/TikTokWebhookSignatureValidator.php';
require_once __DIR__ . '/TikTokShopLiveService.php';
require_once __DIR__ . '/TikTokProductMapper.php';
require_once __DIR__ . '/TikTokProductToMenuSync.php';
require_once __DIR__ . '/TikTokInternalOrderMapper.php';
require_once __DIR__ . '/TikTokOrderToCoreOrder.php';
require_once __DIR__ . '/TikTokProductSyncService.php';
require_once __DIR__ . '/TikTokOrderImportService.php';
require_once __DIR__ . '/TikTokOrderRepository.php';
require_once __DIR__ . '/TikTokBranchSettings.php';
require_once __DIR__ . '/TikTokDashboardQuery.php';
require_once __DIR__ . '/TikTokOAuthService.php';
require_once __DIR__ . '/TikTokWhatsAppNotifier.php';
require_once __DIR__ . '/TikTokLiveAnalyticsService.php';
require_once __DIR__ . '/TikTokCommentAnalyzer.php';
require_once __DIR__ . '/TikTokLiveCopilot.php';
require_once __DIR__ . '/TikTokShopLivePlugin.php';

return [
    'class'       => TikTokShopLivePlugin::class,
    'name'        => 'TikTok Shop Live Connector',
    'version'     => '0.1.0',
    'author'      => 'Kukuh TW',
    'description' => 'Integrasi TikTok Shop Live untuk sinkron katalog, webhook order, event live, rekomendasi AI, dan analitik live commerce.',
    'requires'    => '1.0.0',
];
