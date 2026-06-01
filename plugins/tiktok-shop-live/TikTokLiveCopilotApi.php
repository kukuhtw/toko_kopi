<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/plugin.php';

$metric = [
    'viewers' => (int)($_GET['viewers'] ?? 0),
    'orders' => (int)($_GET['orders'] ?? 0),
    'comments' => (int)($_GET['comments'] ?? 0),
    'revenue' => (float)($_GET['revenue'] ?? 0),
];

$comments = [];

$copilot = new TikTokLiveCopilot(
    new TikTokLiveAnalyticsService(),
    new TikTokCommentAnalyzer()
);

echo json_encode(
    $copilot->generate($metric, $comments),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
