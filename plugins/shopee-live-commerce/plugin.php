<?php

declare(strict_types=1);

require_once __DIR__ . '/ShopeeLiveRepository.php';
require_once __DIR__ . '/ShopeeOpenApiClient.php';
require_once __DIR__ . '/ShopeeWebhookSignatureValidator.php';
require_once __DIR__ . '/ShopeeLiveService.php';
require_once __DIR__ . '/ShopeeProductMapper.php';
require_once __DIR__ . '/ShopeeOrderMapper.php';
require_once __DIR__ . '/ShopeeLiveAnalyticsService.php';
require_once __DIR__ . '/ShopeeCommentAnalyzer.php';
require_once __DIR__ . '/ShopeeLiveCopilot.php';
require_once __DIR__ . '/ShopeeDashboardQuery.php';
require_once __DIR__ . '/ShopeeWhatsAppNotifier.php';
require_once __DIR__ . '/ShopeeLivePlugin.php';

return [
    'class' => ShopeeLivePlugin::class,
    'name' => 'Shopee Live Commerce Connector',
    'version' => '0.1.0',
    'author' => 'Kukuh TW',
    'description' => 'Integrasi Shopee Live untuk order sync, live analytics, voucher insight, AI copilot, dan WhatsApp notification.',
    'requires' => '1.0.0',
];
