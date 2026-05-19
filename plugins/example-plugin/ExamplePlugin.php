<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};

/**
 * Contoh plugin minimal — salin folder ini sebagai template.
 * Ganti nama class, slug folder, dan metadata di plugin.php.
 */
class ExamplePlugin implements PluginInterface
{
    public function getName(): string    { return 'Example Plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'Developer'; }

    public function register(): void
    {
        // Action: dipanggil saat order baru dibuat
        HookManager::addAction('order.created', [$this, 'onOrderCreated']);

        // Filter: modifikasi total harga keranjang
        HookManager::addFilter('cart.total', [$this, 'onCartTotal']);

        // Filter: tambah item ke menu sidebar dashboard cabang
        HookManager::addFilter('dashboard.nav_items', [$this, 'addNavItem']);
    }

    public function onOrderCreated(array $order): void
    {
        // Contoh: log order baru
        error_log('[ExamplePlugin] Order baru: ' . ($order['order_number'] ?? '-'));
    }

    public function onCartTotal(float $total, array $cart): float
    {
        // Contoh: kembalikan $total tanpa dimodifikasi
        return $total;
    }

    public function addNavItem(array $items, string $role): array
    {
        // Contoh: tambah halaman khusus ke sidebar (hanya untuk branch_admin)
        // Gunakan key string (nama section) agar tidak bentrok dengan struktur nav.
        if ($role === 'branch_admin') {
            $items['Plugin'][] = [
                'url'   => '/dashboard/branch/example.php',
                'icon'  => '🔌',
                'label' => 'Example Plugin',
            ];
        }
        return $items;
    }
}
