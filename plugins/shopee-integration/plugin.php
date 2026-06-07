<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/ShopeeIntegrationPlugin.php';
require_once __DIR__ . '/ShopeeIntegrationRepository.php';
require_once __DIR__ . '/ShopeeIntegrationService.php';

return new ShopeeIntegrationPlugin();
