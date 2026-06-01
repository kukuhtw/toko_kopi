<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/plugin.php';

echo json_encode([
    'success' => true,
    'plugin' => 'shopee-live-commerce',
    'version' => '0.1.0',
    'ready' => class_exists('ShopeeLivePlugin') && class_exists('ShopeeLiveRepository')
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
