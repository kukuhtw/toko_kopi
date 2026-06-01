<?php

declare(strict_types=1);

use App\Plugin\HookManager;
use App\Plugin\PluginInterface;

final class ShopeeLivePlugin implements PluginInterface
{
    private ShopeeLiveRepository $repo;

    public function __construct()
    {
        $this->repo = new ShopeeLiveRepository();
    }

    public function getName(): string
    {
        return 'Shopee Live Commerce Connector';
    }

    public function getVersion(): string
    {
        return '0.1.0';
    }

    public function getAuthor(): string
    {
        return 'Kukuh TW';
    }

    public function register(): void
    {
        $this->repo->ensureSchema();
        HookManager::addFilter('dashboard.nav_items', [$this, 'addNavItems'], 18);
    }

    public function addNavItems(array $items, string $role): array
    {
        $items['Integrations'][] = [
            'url' => '/plugins/shopee-live-commerce/dashboard.php',
            'icon' => 'SP',
            'label' => 'Shopee Live'
        ];
        return $items;
    }
}
