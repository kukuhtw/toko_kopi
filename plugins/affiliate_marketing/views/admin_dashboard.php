<?php
require_once __DIR__ . '/../controllers/AffiliateAdminController.php';
$summary = affiliate_admin_dashboard_summary();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Affiliate Admin Dashboard</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;padding:24px;color:#1f2937}
        .wrap{max-width:1200px;margin:0 auto}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
        .card{background:#fff;border-radius:14px;padding:18px;box-shadow:0 4px 16px rgba(0,0,0,.06)}
        .label{font-size:13px;color:#6b7280;margin-bottom:8px}.value{font-size:28px;font-weight:700}
        .nav a{display:inline-block;margin:0 8px 12px 0;padding:10px 14px;background:#111827;color:#fff;border-radius:10px;text-decoration:none;font-size:14px}
    </style>
</head>
<body>
<div class="wrap">
    <h1>Affiliate Admin Dashboard</h1>
    <div class="nav">
        <a href="?page=users">Affiliate Users</a>
        <a href="?page=campaigns">Campaigns</a>
        <a href="?page=traffic">Traffic</a>
        <a href="?page=commissions">Commissions</a>
    </div>
    <div class="grid">
        <div class="card"><div class="label">Total Affiliate</div><div class="value"><?= number_format((float)($summary['total_affiliates'] ?? 0)) ?></div></div>
        <div class="card"><div class="label">Affiliate Aktif</div><div class="value"><?= number_format((float)($summary['active_affiliates'] ?? 0)) ?></div></div>
        <div class="card"><div class="label">Total Order</div><div class="value"><?= number_format((float)($summary['total_orders'] ?? 0)) ?></div></div>
        <div class="card"><div class="label">Total Sales</div><div class="value">Rp <?= number_format((float)($summary['total_sales'] ?? 0),0,',','.') ?></div></div>
        <div class="card"><div class="label">Waiting Commission</div><div class="value">Rp <?= number_format((float)($summary['waiting_commission'] ?? 0),0,',','.') ?></div></div>
        <div class="card"><div class="label">Approved Commission</div><div class="value">Rp <?= number_format((float)($summary['approved_commission'] ?? 0),0,',','.') ?></div></div>
        <div class="card"><div class="label">Paid Commission</div><div class="value">Rp <?= number_format((float)($summary['paid_commission'] ?? 0),0,',','.') ?></div></div>
    </div>
</div>
</body>
</html>
