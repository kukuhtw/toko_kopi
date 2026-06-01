<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/plugin.php';

$response = [
    'success' => true,
    'plugin' => 'tiktok-shop-live',
    'version' => '0.1.0',
    'checks' => [
        'repository' => class_exists('TikTokShopLiveRepository'),
        'oauth' => class_exists('TikTokOAuthService'),
        'analytics' => class_exists('TikTokLiveAnalyticsService'),
        'copilot' => class_exists('TikTokLiveCopilot'),
        'dashboard' => class_exists('TikTokDashboardQuery'),
    ]
];

$response['ready'] = !in_array(false, $response['checks'], true);

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
