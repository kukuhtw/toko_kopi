<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Config/config.php';

use App\Config\Database;
use App\Models\BranchModel;
use App\Helpers\CustomerAuth;
use App\Models\OrderModel;

CustomerAuth::startSession();
CustomerAuth::requireLogin();

$customer = CustomerAuth::customer();
$customerId = (int)($customer['id'] ?? 0);
$orderId = max(0, (int)($_GET['id'] ?? 0));

$orderModel = new OrderModel();
$order = $orderId > 0 ? $orderModel->getWithItems($orderId) : false;

if (!$order || (int)($order['customer_id'] ?? 0) !== $customerId) {
    http_response_code(404);
    exit('Order tidak ditemukan.');
}

$branchModel = new BranchModel();
$branch = $branchModel->find((int)($order['branch_id'] ?? 0)) ?: [];
$branchName = (string)($branch['name'] ?? ('Cabang #' . (int)($order['branch_id'] ?? 0)));
$branchSlug = (string)($branch['slug'] ?? '');

$db = Database::getInstance();
$logsStmt = $db->prepare(
    'SELECT old_status, new_status, note, created_at
     FROM order_status_logs
     WHERE order_id = ?
     ORDER BY created_at DESC, id DESC'
);
$logsStmt->execute([$orderId]);
$logs = $logsStmt->fetchAll();

function customerCurrency(float $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function customerDetailStatusBadgeClass(string $type, ?string $status): string
{
    $normalized = strtolower(trim((string)$status));

    if ($type === 'payment') {
        return match ($normalized) {
            'paid', 'settlement', 'success', 'completed' => 'badge-success',
            'pending', 'waiting', 'unpaid' => 'badge-warning',
            'failed', 'expired', 'cancelled', 'canceled', 'refunded' => 'badge-danger',
            default => 'badge-neutral',
        };
    }

    return match ($normalized) {
        'delivered', 'completed', 'done', 'ready', 'picked_up' => 'badge-success',
        'processing', 'confirmed', 'preparing', 'on_delivery', 'shipped' => 'badge-info',
        'pending', 'new' => 'badge-warning',
        'cancelled', 'canceled', 'failed', 'rejected' => 'badge-danger',
        default => 'badge-neutral',
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Order <?= htmlspecialchars((string)$order['order_number']) ?> - <?= htmlspecialchars(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
  <style>
    body { background:var(--coffee-cream); }
    .customer-shell { max-width:980px; margin:0 auto; padding:24px 20px 48px; }
    .detail-hero {
      background:linear-gradient(135deg, var(--coffee-dark), var(--coffee-brown));
      color:#fff; border-radius:24px; padding:24px; margin-bottom:20px;
      display:flex; justify-content:space-between; gap:14px; flex-wrap:wrap;
    }
    .detail-hero h1 { margin:0 0 8px; font-size:1.55rem; }
    .detail-hero p { margin:0; opacity:.86; }
    .hero-actions { display:flex; gap:10px; flex-wrap:wrap; }
    .hero-actions .btn { border-color:rgba(255,255,255,.28); color:#fff; background:rgba(255,255,255,.08); }
    .hero-actions .btn:hover { background:rgba(255,255,255,.16); }
    .content-grid { display:grid; grid-template-columns:1.15fr .85fr; gap:20px; }
    .card-panel { background:#fff; border:1px solid var(--border); border-radius:20px; padding:20px; }
    .card-panel h2 { margin:0 0 14px; font-size:1.05rem; color:var(--coffee-dark); }
    .table-lite { width:100%; border-collapse:collapse; }
    .table-lite th, .table-lite td { padding:10px 8px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
    .table-lite th { color:var(--text-light); font-size:.8rem; font-weight:600; }
    .summary-row { display:flex; justify-content:space-between; gap:12px; padding:8px 0; }
    .summary-row.total { font-size:1.08rem; font-weight:700; color:var(--coffee-dark); border-top:1px solid var(--border); margin-top:8px; padding-top:12px; }
    .mini-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .mini-box { background:var(--bg-light,#faf9f7); border-radius:14px; padding:12px 14px; }
    .mini-box small { display:block; color:var(--text-light); margin-bottom:6px; }
    .timeline { display:grid; gap:10px; }
    .timeline-item { border-bottom:1px solid var(--border); padding-bottom:10px; }
    .timeline-item:last-child { border-bottom:none; padding-bottom:0; }
    .muted { color:var(--text-light); font-size:.84rem; }
    .badge-soft { display:inline-block; padding:4px 10px; border-radius:999px; background:var(--coffee-cream); color:var(--coffee-brown); font-size:.74rem; font-weight:600; }
    .status-badge { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px; font-size:.76rem; font-weight:700; text-transform:capitalize; }
    .status-badge::before { content:''; width:8px; height:8px; border-radius:50%; background:currentColor; opacity:.75; }
    .badge-success { background:#e6ffef; color:#1f7a3e; }
    .badge-warning { background:#fff4dd; color:#a16207; }
    .badge-danger { background:#ffe7e7; color:#b42318; }
    .badge-info { background:#e8f1ff; color:#1d4ed8; }
    .badge-neutral { background:#f1f3f5; color:#475467; }
    @media (max-width: 860px) {
      .content-grid, .mini-grid { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>
<div class="customer-shell">
  <div class="detail-hero">
    <div>
      <h1><?= htmlspecialchars((string)$order['order_number']) ?></h1>
      <p>Detail lengkap pesanan Anda, termasuk item, status, pembayaran, dan riwayat perubahan status.</p>
    </div>
    <div class="hero-actions">
      <a href="<?= BASE_URL ?>/customer/" class="btn btn-outline">&larr; Kembali ke Dashboard</a>
      <a href="<?= BASE_URL ?>/order.php" class="btn btn-outline">Buat Order Lagi</a>
    </div>
  </div>

  <div class="content-grid">
    <div class="card-panel">
      <h2>Item Pesanan</h2>
      <table class="table-lite">
        <thead>
          <tr>
            <th>Menu</th>
            <th>Qty</th>
            <th>Harga</th>
            <th>Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($order['items'] ?? []) as $item): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars((string)$item['menu_name']) ?></strong>
                <?php if (!empty($item['variant_label'])): ?>
                  <div class="muted">Varian: <?= htmlspecialchars((string)$item['variant_label']) ?></div>
                <?php endif; ?>
                <?php if (!empty($item['notes'])): ?>
                  <div class="muted">Catatan: <?= htmlspecialchars((string)$item['notes']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= (int)$item['quantity'] ?></td>
              <td><?= customerCurrency((float)$item['unit_price']) ?></td>
              <td><?= customerCurrency((float)$item['subtotal']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div style="margin-top:16px">
        <div class="summary-row">
          <span>Subtotal</span>
          <strong><?= customerCurrency((float)$order['subtotal']) ?></strong>
        </div>
        <?php if ((float)$order['discount_amount'] > 0): ?>
          <div class="summary-row">
            <span>Diskon Promo</span>
            <strong>- <?= customerCurrency((float)$order['discount_amount']) ?></strong>
          </div>
        <?php endif; ?>
        <?php if ((int)($order['loyalty_points_redeemed'] ?? 0) > 0): ?>
          <div class="summary-row">
            <span>Loyalty Redeem (<?= number_format((int)$order['loyalty_points_redeemed']) ?> poin)</span>
            <strong>- <?= customerCurrency((float)($order['loyalty_discount_amount'] ?? 0)) ?></strong>
          </div>
        <?php endif; ?>
        <?php if ((float)($order['ppn_amount'] ?? 0) > 0): ?>
          <div class="summary-row">
            <span>PPN (<?= (float)$order['ppn_rate'] ?>%)</span>
            <strong><?= customerCurrency((float)$order['ppn_amount']) ?></strong>
          </div>
        <?php endif; ?>
        <div class="summary-row total">
          <span>Total</span>
          <span><?= customerCurrency((float)$order['total_amount']) ?></span>
        </div>
      </div>
    </div>

    <div style="display:grid;gap:20px">
      <div class="card-panel">
        <h2>Ringkasan Order</h2>
        <div class="mini-grid">
          <div class="mini-box">
            <small>Status Order</small>
            <strong><span class="status-badge <?= customerDetailStatusBadgeClass('order', (string)$order['order_status']) ?>"><?= htmlspecialchars((string)$order['order_status']) ?></span></strong>
          </div>
          <div class="mini-box">
            <small>Status Pembayaran</small>
            <strong><span class="status-badge <?= customerDetailStatusBadgeClass('payment', (string)$order['payment_status']) ?>"><?= htmlspecialchars((string)$order['payment_status']) ?></span></strong>
          </div>
          <div class="mini-box">
            <small>Tanggal Order</small>
            <strong><?= date('d/m/Y H:i', strtotime((string)$order['created_at'])) ?></strong>
          </div>
          <div class="mini-box">
            <small>Cabang</small>
            <strong><?= htmlspecialchars($branchName) ?></strong>
          </div>
        </div>
        <div class="mini-box" style="margin-top:12px">
          <small>Alamat Pengiriman</small>
          <strong><?= htmlspecialchars((string)($order['delivery_address'] ?: '-')) ?></strong>
        </div>
        <div class="mini-box" style="margin-top:12px">
          <small>Catatan Order</small>
          <strong><?= htmlspecialchars((string)($order['notes'] ?: '-')) ?></strong>
        </div>
      </div>

      <div class="card-panel">
        <h2>Riwayat Status</h2>
        <div class="timeline">
          <?php foreach ($logs as $log): ?>
            <div class="timeline-item">
              <strong><span class="status-badge <?= customerDetailStatusBadgeClass('order', (string)$log['new_status']) ?>"><?= htmlspecialchars((string)$log['new_status']) ?></span></strong>
              <?php if (!empty($log['old_status'])): ?>
                <div class="muted">Dari <?= htmlspecialchars((string)$log['old_status']) ?></div>
              <?php endif; ?>
              <?php if (!empty($log['note'])): ?>
                <div class="muted"><?= htmlspecialchars((string)$log['note']) ?></div>
              <?php endif; ?>
              <div class="muted"><?= date('d/m/Y H:i', strtotime((string)$log['created_at'])) ?></div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($logs)): ?>
            <div class="muted">Belum ada riwayat status untuk order ini.</div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($branchSlug !== ''): ?>
      <div class="card-panel">
        <h2>Pesan Lagi</h2>
        <div class="muted" style="margin-bottom:12px">
          Ingin ulang pesanan dari cabang yang sama? Buka halaman order cabang ini lalu pilih menu favorit Anda lagi.
        </div>
        <a href="<?= BASE_URL ?>/order.php?branch=<?= urlencode($branchSlug) ?>" class="btn btn-primary" style="width:100%">
          Repeat Order di <?= htmlspecialchars($branchName) ?>
        </a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
