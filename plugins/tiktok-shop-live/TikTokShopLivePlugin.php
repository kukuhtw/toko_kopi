<?php

declare(strict_types=1);

use App\Plugin\HookManager;
use App\Plugin\PluginInterface;

final class TikTokShopLivePlugin implements PluginInterface
{
    private TikTokShopLiveRepository $repo;

    public function __construct()
    {
        $this->repo=new TikTokShopLiveRepository();
    }

    public function getName(): string
    {
        return 'TikTok Shop Live Connector';
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

        HookManager::addFilter('dashboard.nav_items',[$this,'addNavItems'],18);
    }

    public function addNavItems(array $items,string $role): array
    {
        $items['Integrations'][]=[
            'url'=>'/dashboard/tiktok-live.php',
            'icon'=>'TT',
            'label'=>'TikTok Shop Live'
        ];

        return $items;
    }
}
