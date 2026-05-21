<?php

declare(strict_types=1);

namespace App\Helpers;

/** Minimal shared layout renderer for dashboard pages */
class View
{
    public static function renderLayout(string $title, string $content, string $role = 'super_admin'): string
    {
        $user          = Auth::user();
        $initial       = strtoupper(substr($user['name'], 0, 1));
        $baseUrl       = BASE_URL;
        $navItems      = \App\Plugin\HookManager::applyFilters('dashboard.nav_items', self::getNavItems($role), $role);
        $topbarActions = \App\Plugin\HookManager::applyFilters('dashboard.topbar_actions', '', (int)($user['branch_id'] ?? 0), $role);
        $headStyles    = \App\Plugin\HookManager::applyFilters('dashboard.head_styles', '');
        $brandHtml     = \App\Plugin\HookManager::applyFilters('dashboard.brand_html', '&#9749; Toko <span>Kopi</span>');
        $appName       = \App\Plugin\HookManager::applyFilters('dashboard.app_name', APP_NAME);
        $cssPath       = PUBLIC_PATH . '/assets/css/app.css';
        $jsPath        = PUBLIC_PATH . '/assets/js/app.js';
        $cssVersion    = is_file($cssPath) ? (string) filemtime($cssPath) : APP_VERSION;
        $jsVersion     = is_file($jsPath) ? (string) filemtime($jsPath) : APP_VERSION;

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> - <?= htmlspecialchars($appName) ?></title>
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/app.css?v=<?= urlencode($cssVersion) ?>">
  <?= $headStyles ?>
</head>
<body>
<button type="button" class="sidebar-edge-toggle" id="sidebarEdgeToggle" aria-label="Sembunyikan menu" onclick="window.toggleDashboardSidebar && window.toggleDashboardSidebar()"><-</button>
<div class="page-wrapper">
  <div class="sidebar-backdrop" id="dashboardSidebarBackdrop" aria-hidden="true"></div>
  <aside class="sidebar" id="dashboardSidebar">
    <div class="sidebar-logo-wrap">
      <div class="sidebar-logo"><?= $brandHtml ?></div>
    </div>
    <nav class="sidebar-nav">
      <?php foreach ($navItems as $section => $items): ?>
      <div class="nav-section"><?= htmlspecialchars((string)$section) ?></div>
      <?php foreach ($items as $item): ?>
      <a href="<?= $baseUrl . $item['url'] ?>"
         class="nav-item <?= self::isActive($item['url']) ? 'active' : '' ?>">
        <span class="icon"><?= $item['icon'] ?></span>
        <?= htmlspecialchars($item['label']) ?>
      </a>
      <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">v<?= APP_VERSION ?></div>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <div class="topbar-title-wrap">
        <button type="button" class="sidebar-toggle sidebar-toggle-topbar" id="sidebarToggleBtn" aria-label="Tampilkan menu" onclick="window.toggleDashboardSidebar && window.toggleDashboardSidebar()">=</button>
        <div class="topbar-title"><?= htmlspecialchars($title) ?></div>
      </div>
      <?= $topbarActions ?>
      <div class="topbar-user">
        <div class="avatar"><?= $initial ?></div>
        <div class="topbar-user-meta">
          <div style="font-weight:600"><?= htmlspecialchars($user['name']) ?></div>
          <div style="font-size:.75rem;color:var(--text-light)"><?= htmlspecialchars(str_replace('_', ' ', $user['role'])) ?></div>
        </div>
        <a href="<?= $baseUrl ?>/logout.php" class="btn btn-sm btn-outline topbar-logout">Logout</a>
      </div>
    </header>
    <main class="page-body">
      <?= $content ?>
    </main>
  </div>
</div>
<script src="<?= $baseUrl ?>/assets/js/app.js?v=<?= urlencode($jsVersion) ?>"></script>
<script>
(function () {
    var root = document.documentElement;
    var toggleBtn = document.getElementById('sidebarToggleBtn');
    var edgeBtn = document.getElementById('sidebarEdgeToggle');
    var backdrop = document.getElementById('dashboardSidebarBackdrop');
    var storageKey = 'kopibot_sidebar_collapsed';

    function isMobile() {
        return window.innerWidth <= 768;
    }

    function updateToggleButton() {
        if (!toggleBtn) {
            return;
        }

        if (isMobile()) {
            var mobileOpen = root.classList.contains('sidebar-mobile-open');
            toggleBtn.textContent = mobileOpen ? 'x' : '=';
            toggleBtn.setAttribute('aria-label', mobileOpen ? 'Sembunyikan menu' : 'Tampilkan menu');
        } else {
            var collapsed = root.classList.contains('sidebar-collapsed');
            toggleBtn.textContent = '=';
            toggleBtn.setAttribute('aria-label', collapsed ? 'Tampilkan menu' : 'Sembunyikan menu');
        }

        if (edgeBtn) {
            var edgeCollapsed = root.classList.contains('sidebar-collapsed');
            edgeBtn.textContent = edgeCollapsed ? '->' : '<-';
            edgeBtn.setAttribute('aria-label', edgeCollapsed ? 'Tampilkan menu' : 'Sembunyikan menu');
        }
    }

    function setCollapsed(collapsed) {
        root.classList.toggle('sidebar-collapsed', collapsed);
        try {
            localStorage.setItem(storageKey, collapsed ? '1' : '0');
        } catch (e) {
            // Ignore storage issues and keep the UI working.
        }
        updateToggleButton();
    }

    var savedCollapsed = false;
    try {
        savedCollapsed = localStorage.getItem(storageKey) === '1';
    } catch (e) {
        savedCollapsed = false;
    }
    root.classList.toggle('sidebar-collapsed', savedCollapsed);
    updateToggleButton();

    window.toggleDashboardSidebar = function () {
        if (isMobile()) {
            root.classList.toggle('sidebar-mobile-open');
            updateToggleButton();
            return false;
        }

        setCollapsed(!root.classList.contains('sidebar-collapsed'));
        return false;
    };

    document.addEventListener('click', function (event) {
        if (window.innerWidth > 768 || !root.classList.contains('sidebar-mobile-open')) {
            return;
        }

        var sidebar = document.getElementById('dashboardSidebar');
        var clickedInsideSidebar = sidebar && sidebar.contains(event.target);
        var clickedToggle = (toggleBtn && toggleBtn.contains(event.target)) || (edgeBtn && edgeBtn.contains(event.target));
        if (!clickedInsideSidebar && !clickedToggle) {
            root.classList.remove('sidebar-mobile-open');
            updateToggleButton();
        }
    });

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            root.classList.remove('sidebar-mobile-open');
            updateToggleButton();
        });
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            root.classList.remove('sidebar-mobile-open');
        }
        updateToggleButton();
    });
})();
</script>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    private static function isActive(string $url): bool
    {
        $current = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return $current === $url || str_starts_with($current, $url);
    }

    private static function getNavItems(string $role): array
    {
        if ($role === 'super_admin') {
            return [
                'Overview' => [
                    ['url' => '/dashboard/super/', 'icon' => 'DA', 'label' => 'Dashboard'],
                ],
                'Management' => [
                    ['url' => '/dashboard/super/branches.php', 'icon' => 'CB', 'label' => 'Cabang'],
                    ['url' => '/dashboard/super/users.php', 'icon' => 'US', 'label' => 'Users'],
                    ['url' => '/dashboard/super/menu.php', 'icon' => 'MN', 'label' => 'Menu Global'],
                    ['url' => '/dashboard/super/toppings.php', 'icon' => 'TP', 'label' => 'Topping'],
                    ['url' => '/dashboard/super/promos.php', 'icon' => 'PR', 'label' => 'Promo Global'],
                ],
                'Monitoring' => [
                    ['url' => '/dashboard/super/orders.php', 'icon' => 'OR', 'label' => 'Semua Order'],
                    ['url' => '/dashboard/super/conversations.php', 'icon' => 'CH', 'label' => 'Conversations'],
                    ['url' => '/dashboard/super/agent-monitor.php', 'icon' => 'AI', 'label' => 'Agent Monitor'],
                    ['url' => '/dashboard/super/token-usage.php', 'icon' => 'TK', 'label' => 'Token Usage'],
                ],
                'Settings' => [
                    ['url' => '/dashboard/super/settings.php', 'icon' => 'ST', 'label' => 'App Settings'],
                    ['url' => '/dashboard/super/whatsapp.php', 'icon' => 'WA', 'label' => 'WhatsApp'],
                    ['url' => '/dashboard/super/plugins.php', 'icon' => 'PL', 'label' => 'Plugins'],
                ],
            ];
        }

        return [
            'Overview' => [
                ['url' => '/dashboard/branch/', 'icon' => 'DA', 'label' => 'Dashboard'],
            ],
            'Produk' => [
                ['url' => '/dashboard/branch/menu.php', 'icon' => 'MN', 'label' => 'Menu'],
                ['url' => '/dashboard/branch/promos.php', 'icon' => 'PR', 'label' => 'Promo'],
            ],
            'Order' => [
                ['url' => '/dashboard/branch/orders.php', 'icon' => 'OR', 'label' => 'Order Masuk'],
                ['url' => '/dashboard/branch/history.php', 'icon' => 'HS', 'label' => 'History Transaksi'],
                ['url' => '/dashboard/branch/conversations.php', 'icon' => 'CH', 'label' => 'Percakapan'],
                ['url' => '/dashboard/branch/agent-monitor.php', 'icon' => 'AI', 'label' => 'Agent Monitor'],
            ],
            'Settings' => [
                ['url' => '/dashboard/branch/settings.php', 'icon' => 'ST', 'label' => 'Pengaturan'],
                ['url' => '/dashboard/branch/whatsapp.php', 'icon' => 'WA', 'label' => 'WhatsApp Bot'],
            ],
        ];
    }
}
