<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Currency};
use App\Config\Database;

Auth::startSession();
Auth::requireLogin();

$user     = Auth::user();
$branchId = (int)$user['branch_id'];
if (!$branchId) { http_response_code(403); exit; }

$db = Database::getInstance();

// Filters
$dateFrom = $_GET['from']   ?? date('Y-m-01');
$dateTo   = $_GET['to']     ?? date('Y-m-d');
$status   = $_GET['status'] ?? 'completed';
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 25;
$offset   = ($page - 1) * $limit;

$allowedStatuses = ['completed', 'cancelled', 'all'];
if (!in_array($status, $allowedStatuses)) { $status = 'completed'; }

$currency = (new \App\Models\BranchModel())->getCurrency($branchId);

// Build query
$statusClause = $status === 'all'
    ? "o.order_status IN ('completed','cancelled')"
    : "o.order_status = ?";

$params = $status === 'all'
    ? [$branchId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
    : [$branchId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59', $status];

$countParams = $params;

$orders = $db->prepare(
    "SELECT o.*, c.name AS customer_display_name
     FROM orders o
     JOIN customers c ON o.customer_id = c.id
     WHERE o.branch_id = ? AND {$statusClause}
       AND o.created_at BETWEEN ? AND ?
     ORDER BY o.created_at DESC
     LIMIT {$limit} OFFSET {$offset}"
);
$orders->execute($params);
$orders = $orders->fetchAll();

$totalRow = $db->prepare(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(o.total_amount),0) AS revenue,
            COALESCE(SUM(CASE WHEN o.payment_status='paid' THEN o.total_amount ELSE 0 END),0) AS paid_revenue
     FROM orders o
     WHERE o.branch_id = ? AND {$statusClause}
       AND o.created_at BETWEEN ? AND ?"
);
$totalRow->execute($countParams);
$summary = $totalRow->fetch();

$total = (int)$summary['cnt'];
$pages = $total > 0 ? (int)ceil($total / $limit) : 1;

ob_start();
?>

<?php $exportUrl = BASE_URL . '/api/export/history-branch.php?' . http_build_query(['from' => $dateFrom, 'to' => $dateTo, 'status' => $status]); ?>
<div class="section-header">
  <h2>History Transaksi</h2>
  <div style="display:flex;align-items:center;gap:12px">
    <span style="color:var(--text-light);font-size:.9rem"><?= number_format($total) ?> transaksi</span>
    <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-outline" style="font-size:.85rem">&#8595; Export CSV</a>
  </div>
</div>

<!-- Filter -->
<div class="card" style="margin-bottom:16px">
  <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
    <div class="form-group" style="margin:0;flex:1;min-width:140px">
      <label class="form-label">Dari Tanggal</label>
      <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:140px">
      <label class="form-label">Sampai Tanggal</label>
      <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
    </div>
    <div class="form-group" style="margin:0;min-width:140px">
      <label class="form-label">Status</label>
      <select name="status" class="form-control">
        <option value="completed" <?= $status==='completed' ? 'selected' : '' ?>>Completed</option>
        <option value="cancelled" <?= $status==='cancelled' ? 'selected' : '' ?>>Cancelled</option>
        <option value="all"       <?= $status==='all'       ? 'selected' : '' ?>>Semua</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>&status=completed" class="btn btn-outline">Reset</a>
  </form>
</div>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:16px">
  <div class="stat-card orange">
    <div class="stat-label">Total Transaksi</div>
    <div class="stat-value"><?= number_format($total) ?></div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Total Pendapatan</div>
    <div class="stat-value"><?= Currency::format((float)$summary['revenue'], $currency) ?></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-label">Sudah Dibayar</div>
    <div class="stat-value"><?= Currency::format((float)$summary['paid_revenue'], $currency) ?></div>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>No. Order</th>
          <th>Customer</th>
          <th>Channel</th>
          <th>Total</th>
          <th>Status Order</th>
          <th>Payment</th>
          <th>Tanggal</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
      <?php
        $statusBadge = match($o['order_status']) {
            'completed' => 'badge-green',
            'cancelled' => 'badge-red',
            default     => 'badge-gray',
        };
      ?>
      <tr>
        <td><strong><a href="<?= BASE_URL ?>/dashboard/branch/order-detail.php?id=<?= $o['id'] ?>"><?= htmlspecialchars($o['order_number']) ?></a></strong></td>
        <td>
          <?= htmlspecialchars($o['customer_name']) ?>
          <?php if ($o['customer_wa']): ?><br><small style="color:var(--text-light)">📱 <?= htmlspecialchars($o['customer_wa']) ?></small><?php endif; ?>
        </td>
        <td><span class="badge <?= $o['channel']==='whatsapp' ? 'badge-green' : 'badge-blue' ?>"><?= $o['channel'] ?></span></td>
        <td><?= Currency::format((float)$o['total_amount'], $currency) ?></td>
        <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($o['order_status']) ?></span></td>
        <td><span class="badge <?= $o['payment_status']==='paid' ? 'badge-green' : 'badge-gray' ?>"><?= $o['payment_status'] ?></span></td>
        <td style="font-size:.8rem;white-space:nowrap"><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
        <td><a href="<?= BASE_URL ?>/dashboard/branch/order-detail.php?id=<?= $o['id'] ?>" class="btn btn-xs btn-outline">Detail</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($orders)): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--text-light);padding:32px">Tidak ada transaksi pada periode ini</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php
    $q = http_build_query(['from' => $dateFrom, 'to' => $dateTo, 'status' => $status]);
    for ($p = 1; $p <= $pages; $p++):
    ?>
    <a href="?<?= $q ?>&page=<?= $p ?>" class="page-btn <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
echo View::renderLayout('History Transaksi', $content, 'branch_admin');
