<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};

class CmsBeritaPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'CMS Berita Toko';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAuthor(): string
    {
        return 'Codex';
    }

    public function register(): void
    {
        HookManager::addFilter('dashboard.nav_items', [$this, 'addNavItems'], 12);
    }

    public function addNavItems(array $items, string $role): array
    {
        if ($role === 'super_admin') {
            $items['Management'][] = [
                'url'   => '/dashboard/super/berita.php',
                'icon'  => '📰',
                'label' => 'Berita Toko',
            ];
            return $items;
        }

        if ($role === 'branch_admin') {
            $items['Produk'][] = [
                'url'   => '/dashboard/branch/berita.php',
                'icon'  => '📰',
                'label' => 'Berita Cabang',
            ];
        }

        return $items;
    }
}
