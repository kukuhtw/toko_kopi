<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};

class KitchenDisplayPlugin implements PluginInterface
{
    public function getName(): string    { return 'Kitchen Display'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'KopiBot Team'; }

    public function register(): void
    {
        HookManager::addFilter('dashboard.nav_items', [$this, 'addNavItem'], 20);
    }

    public function addNavItem(array $items, string $role): array
    {
        if ($role !== 'branch_admin') {
            return $items;
        }

        if (isset($items['Order'])) {
            $items['Order'][] = [
                'url'   => '/dashboard/branch/kitchen-display.php',
                'icon'  => 'KD',
                'label' => 'Kitchen Display',
            ];
        }

        return $items;
    }
}
