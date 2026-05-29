<?php
require_once __DIR__ . '/../controllers/AffiliatePortalController.php';
$profile = affiliate_portal_profile();
$summary = affiliate_portal_summary();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Affiliate Portal</title>
<style>
body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;padding:24px;color:#1f2937}
.wrap{max-width:1200px;margin:0 auto}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
.card{background:#fff;border-radius:14px;padding:18px;box-shadow:0 4px 16px rgba(0,0,0,.06)}
.label{font-size:13px;color:#6b7280}.value{font-size:26px;font-weight:700;margin-top:8px}
</style>
</head>
<body>
<div class="wrap">
<h1>Affiliate Portal</h1>
<p><strong><?= htmlspecialchars($profile['name'] ?? '') ?></strong> | <?= htmlspecialchars($profile['affiliate_code'] ?? '') ?></p>
<div class="grid">
<div class="card"><div class="label">Total Click</div><div class="value"><?= number_format((float)($summary['total_clicks'] ?? 0)) ?></div></div>
<div class="card"><div class="label">Total Order</div><div class="value"><?= number_format((float)($summary['total_orders'] ?? 0)) ?></div></div>
<div class="card"><div class="label">Conversion Rate</div><div class="value"><?= number_format((float)($summary['conversion_rate'] ?? 0),2) ?>%</div></div>
<div class="card"><div class="label">Total Sales</div><div class="value">Rp <?= number_format((float)($summary['total_sales'] ?? 0),0,',','.') ?></div></div>
<div class="card"><div class="label">Waiting Commission</div><div class="value">Rp <?= number_format((float)($summary['waiting_commission'] ?? 0),0,',','.') ?></div></div>
<div class="card"><div class="label">Approved Commission</div><div class="value">Rp <?= number_format((float)($summary['approved_commission'] ?? 0),0,',','.') ?></div></div>
<div class="card"><div class="label">Paid Commission</div><div class="value">Rp <?= number_format((float)($summary['paid_commission'] ?? 0),0,',','.') ?></div></div>
</div>
</div>
</body>
</html>
