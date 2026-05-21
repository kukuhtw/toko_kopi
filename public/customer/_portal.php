<?php

declare(strict_types=1);

if (!function_exists('customerPortalBaseUrl')) {
    function customerPortalBaseUrl(): string
    {
        return defined('BASE_URL') ? (string) BASE_URL : '';
    }
}

if (!function_exists('customerPortalLinks')) {
    function customerPortalLinks(): array
    {
        $base = customerPortalBaseUrl();

        return [
            'overview' => $base . '/customer/',
            'orders' => $base . '/customer/orders.php',
            'loyalty' => $base . '/customer/loyalty.php',
            'profile' => $base . '/customer/profile.php',
        ];
    }
}

if (!function_exists('customerPortalRenderStart')) {
    function customerPortalRenderStart(array $config): void
    {
        $title = (string) ($config['title'] ?? 'Customer Portal');
        $heading = (string) ($config['heading'] ?? 'Customer Portal');
        $subtitle = (string) ($config['subtitle'] ?? '');
        $active = (string) ($config['active'] ?? 'overview');
        $extraStyles = (string) ($config['extra_styles'] ?? '');
        $links = customerPortalLinks();
        $navLabels = [
            'overview' => 'Overview',
            'orders' => 'Orders',
            'loyalty' => 'Loyalty',
            'profile' => 'Profile',
        ];
        ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars(customerPortalBaseUrl()) ?>/assets/css/app.css">
  <style>
    body { background:var(--coffee-cream); }
    .customer-shell { max-width:1180px; margin:0 auto; padding:24px 20px 48px; }
    .customer-hero {
      background:linear-gradient(135deg, var(--coffee-dark), var(--coffee-brown));
      color:#fff; border-radius:24px; padding:28px; margin-bottom:20px;
      display:flex; justify-content:space-between; gap:18px; flex-wrap:wrap;
    }
    .customer-hero h1 { margin:0 0 8px; font-size:1.8rem; }
    .customer-hero p { margin:0; opacity:.86; }
    .hero-actions { display:flex; gap:10px; align-items:flex-start; flex-wrap:wrap; }
    .hero-actions .btn { border-color:rgba(255,255,255,.28); color:#fff; background:rgba(255,255,255,.08); }
    .hero-actions .btn:hover { background:rgba(255,255,255,.16); }
    .portal-nav { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; }
    .portal-link { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; background:#fff; border:1px solid var(--border); color:var(--coffee-dark); text-decoration:none; font-weight:600; }
    .portal-link:hover { border-color:var(--coffee-brown); color:var(--coffee-brown); }
    .portal-link.active { background:var(--coffee-brown); border-color:var(--coffee-brown); color:#fff; }
    .card-panel { background:#fff; border:1px solid var(--border); border-radius:20px; padding:20px; }
    .card-panel h2 { margin:0 0 14px; font-size:1.1rem; color:var(--coffee-dark); }
    .table-wrap { width:100%; overflow-x:auto; }
    .mini-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .mini-box { background:var(--bg-light,#faf9f7); border-radius:14px; padding:12px 14px; }
    .mini-box small { display:block; color:var(--text-light); margin-bottom:6px; }
    .muted { color:var(--text-light); font-size:.84rem; }
    .row-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
    .badge-soft { display:inline-block; padding:4px 10px; border-radius:999px; background:var(--coffee-cream); color:var(--coffee-brown); font-size:.74rem; font-weight:600; }
    .status-badge { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px; font-size:.76rem; font-weight:700; text-transform:capitalize; }
    .status-badge::before { content:''; width:8px; height:8px; border-radius:50%; background:currentColor; opacity:.75; }
    .badge-success { background:#e6ffef; color:#1f7a3e; }
    .badge-warning { background:#fff4dd; color:#a16207; }
    .badge-danger { background:#ffe7e7; color:#b42318; }
    .badge-info { background:#e8f1ff; color:#1d4ed8; }
    .badge-neutral { background:#f1f3f5; color:#475467; }
    .table-lite { width:100%; border-collapse:collapse; }
    .table-lite th, .table-lite td { padding:10px 8px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
    .table-lite th { color:var(--text-light); font-size:.8rem; font-weight:600; }
    .table-lite td strong,
    .table-lite td a { word-break:break-word; }
    @media (max-width: 980px) {
      .customer-shell { padding:20px 16px 40px; }
      .mini-grid { grid-template-columns:1fr; }
    }
    @media (max-width: 640px) {
      .customer-shell { padding:16px 12px 32px; }
      .customer-hero { padding:20px 16px; border-radius:20px; }
      .customer-hero h1 { font-size:1.45rem; line-height:1.25; }
      .hero-actions { width:100%; }
      .hero-actions .btn { flex:1 1 100%; justify-content:center; }
      .portal-nav { gap:8px; }
      .portal-link { flex:1 1 calc(50% - 8px); justify-content:center; text-align:center; padding:10px 12px; }
      .card-panel { padding:16px; border-radius:18px; }
      .row-head { flex-direction:column; }
      .table-lite th, .table-lite td { padding:10px 6px; font-size:.84rem; }
    }
<?= $extraStyles !== '' ? $extraStyles . "\n" : '' ?>  </style>
</head>
<body>
<div class="customer-shell">
  <div class="customer-hero">
    <div>
      <h1><?= htmlspecialchars($heading) ?></h1>
      <p><?= htmlspecialchars($subtitle) ?></p>
    </div>
    <div class="hero-actions">
      <a href="<?= htmlspecialchars(customerPortalBaseUrl()) ?>/" class="btn btn-outline">Beranda</a>
      <a href="<?= htmlspecialchars(customerPortalBaseUrl()) ?>/order.php" class="btn btn-outline">Buat Order</a>
      <a href="<?= htmlspecialchars(customerPortalBaseUrl()) ?>/customer/logout.php" class="btn btn-outline">Logout</a>
    </div>
  </div>

  <div class="portal-nav">
    <?php foreach ($navLabels as $key => $label): ?>
      <a href="<?= htmlspecialchars($links[$key]) ?>" class="portal-link<?= $active === $key ? ' active' : '' ?>">
        <?= htmlspecialchars($label) ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php
    }
}

if (!function_exists('customerPortalRenderEnd')) {
    function customerPortalRenderEnd(): void
    {
        ?>
</div>
</body>
</html>
<?php
    }
}
