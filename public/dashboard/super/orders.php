<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Currency};
use App\Config\Database;
use App\Models\BranchModel;

Auth::startSession();
Auth::requireRole('super_admin');

$db = Database::getInstance();

// ── Filter params ─────────────────────────────────────────
$page     = max(1, (int)($_GET['page']    ?? 1));
$branchId = (int)($_GET['branch']         ?? 0);
$q        = trim($_GET['q']               ?? '');
$status   = $_GET['status']               ?? '';
$payment  = $_GET['payment']              ?? '';
$period   = $_GET['period']               ?? '';
$limit    = 25;
$offset   = ($page - 1) * $limit;

if (!in_array($status,  ['', 'pending', 'processing', 'completed', 'cancelled'], true)) { $status  = ''; }
if (!in_array($payment, ['', 'paid', 'unpaid'], true))                                  { $payment = ''; }
if (!in_array($period,  ['', 'today', '7d', '30d'], true))                             { $period  = ''; }

$dateFrom = $dateTo = '';
match ($period) {
    'today' => [$dateFrom, $dateTo] = [date('Y-m-d'), date('Y-m-d')],
    '7d'    => [$dateFrom, $dateTo] = [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
    '30d'   => [$dateFrom, $dateTo] = [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
    default => null,
};

$hasFilter = $branchId > 0 || $q !== '' || $status !== '' || $payment !== '' || $period !== '';

// ── Build WHERE clause ─────────────────────────────────────
$idrRateExpr = "COALESCE(
    CAST(bs_rate.setting_val AS DECIMAL(15,4)),
    CASE COALESCE(bs_cur.setting_val,'IDR')
        WHEN 'SGD' THEN 11500
        WHEN 'AUD' THEN 10000
        WHEN 'USD' THEN 15800
        WHEN 'MYR' THEN 3400
        ELSE 1
    END
)";

$conditions = [];
$whereParams = [];

if ($branchId > 0) {
    $conditions[]  = 'o.branch_id = ?';
    $whereParams[] = $branchId;
}
if ($q !== '') {
    $conditions[]  = '(o.order_number LIKE ? OR o.customer_name LIKE ?)';
    $like          = '%' . $q . '%';
    $whereParams[] = $like;
    $whereParams[] = $like;
}
if ($status !== '') {
    $conditions[]  = 'o.order_status = ?';
    $whereParams[] = $status;
}
if ($payment !== '') {
    $conditions[]  = 'o.payment_status = ?';
    $whereParams[] = $payment;
}
if ($dateFrom !== '') {
    $conditions[]  = 'DATE(o.created_at) >= ?';
    $whereParams[] = $dateFrom;
}
if ($dateTo !== '') {
    $conditions[]  = 'DATE(o.created_at) <= ?';
    $whereParams[] = $dateTo;
}

$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// ── Query orders ──────────────────────────────────────────
$orders = $db->prepare(
    "SELECT o.*,
            b.name AS branch_name,
            COALESCE(bs_cur.setting_val, 'IDR')         AS branch_currency,
            o.total_amount * {$idrRateExpr}              AS total_idr
     FROM orders o
     JOIN branches b ON o.branch_id = b.id
     LEFT JOIN branch_settings bs_cur  ON bs_cur.branch_id  = o.branch_id AND bs_cur.setting_key  = 'currency'
     LEFT JOIN branch_settings bs_rate ON bs_rate.branch_id = o.branch_id AND bs_rate.setting_key = 'idr_rate'
     {$whereClause}
     ORDER BY o.created_at DESC
     LIMIT ? OFFSET ?"
);
$orders->execute([...$whereParams, $limit, $offset]);
$orders = $orders->fetchAll();

// ── Count ─────────────────────────────────────────────────
$countStmt = $db->prepare("SELECT COUNT(*) FROM orders o {$whereClause}");
$countStmt->execute($whereParams);
$total = (int)$countStmt->fetchColumn();
$pages = (int)ceil($total / $limit);

// Branches for dropdown
$branches = (new BranchModel())->getActive();

// Build filter query string
$filterQuery = http_build_query(array_filter([
    'branch'  => $branchId ?: '',
    'q'       => $q,
    'status'  => $status,
    'payment' => $payment,
    'period'  => $period,
]));
$paginateUrl = '?' . ($filterQuery ? $filterQuery . '&' : '') . 'page=';
$exportUrl   = BASE_URL . '/api/export/orders-super.php' . ($filterQuery ? '?' . $filterQuery : '');

ob_start();
?>
<div class="section-header">
  <h2>Semua Order</h2>
  <div class="section-actions">
    <span style="color:var(--text-light);font-size:.9rem">
      <?= number_format($total) ?> <?= $hasFilter ? 'hasil filter' : 'total order' ?>
    </span>
    <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-outline" style="font-size:.85rem">
      <span aria-hidden="true">&#8595;</span> Export CSV</a>
  </div>
</div>

<!-- Filter Bar -->
<form method="GET" class="card" style="margin-bottom:16px;padding:14px 16px">
  <div class="filter-grid">
    <div class="filter-grow">
      <label for="sf_q" style="font-size:.8rem;color:var(--text-mid);display:block;margin-bottom:4px">Cari order / customer</label>
      <input type="text" id="sf_q" name="q" class="form-control compact-input"
             value="<?= htmlspecialchars($q) ?>" placeholder="No. order atau nama customer…">
    </div>
    <div class="filter-field">
      <label for="sf_branch" style="font-size:.8rem;color:var(--text-mid);display:block;margin-bottom:4px">Cabang</label>
      <select id="sf_branch" name="branch" class="form-control compact-select">
        <option value="0">Semua Cabang</option>
        <?php foreach ($branches as $b): ?>
        <option value="<?= $b['id'] ?>" <?= $branchId === (int)$b['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($b['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-field">
      <label for="sf_status" style="font-size:.8rem;color:var(--text-mid);display:block;margin-bottom:4px">Status</label>
      <select id="sf_status" name="status" class="form-control compact-select">
        <option value="">Semua Status</option>
        <?php foreach (['pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $v => $l): ?>
        <option value="<?= $v ?>" <?= $status === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-field">
      <label for="sf_payment" style="font-size:.8rem;color:var(--text-mid);display:block;margin-bottom:4px">Pembayaran</label>
      <select id="sf_payment" name="payment" class="form-control compact-select">
        <option value="">Semua</option>
        <option value="paid"   <?= $payment === 'paid'   ? 'selected' : '' ?>>Paid</option>
        <option value="unpaid" <?= $payment === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
      </select>
    </div>
    <div class="filter-field">
      <label for="sf_period" style="font-size:.8rem;color:var(--text-mid);display:block;margin-bottom:4px">Periode</label>
      <select id="sf_period" name="period" class="form-control compact-select">
        <option value="">Semua</option>
        <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Hari ini</option>
        <option value="7d"    <?= $period === '7d'    ? 'selected' : '' ?>>7 Hari</option>
        <option value="30d"   <?= $period === '30d'   ? 'selected' : '' ?>>30 Hari</option>
      </select>
    </div>
    <div class="button-row">
      <button type="submit" class="btn btn-primary" style="padding:6px 16px">Filter</button>
      <?php if ($hasFilter): ?>
      <a href="?" class="btn btn-outline" style="padding:6px 12px">Reset</a>
      <?php endif; ?>
    </div>
  </div>
</form>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>No. Order</th><th>Cabang</th><th>Customer</th>
          <th>Channel</th><th>Total (~IDR)</th><th>Status</th><th>Payment</th><th>Waktu</th><th>Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
      <?php $isLocal = $o['branch_currency'] !== 'IDR'; ?>
      <tr>
        <td><a href="<?= BASE_URL ?>/dashboard/super/order-detail.php?id=<?= $o['id'] ?>"
               style="font-weight:600" aria-label="Detail order <?= htmlspecialchars($o['order_number']) ?>">
          <?= htmlspecialchars($o['order_number']) ?>
        </a></td>
        <td><?= htmlspecialchars($o['branch_name'] ?? '-') ?></td>
        <td>
          <?= htmlspecialchars($o['customer_name']) ?>
          <?php if ($o['customer_wa']): ?>
            <br><small style="color:var(--text-light)">📱 <?= htmlspecialchars($o['customer_wa']) ?></small>
          <?php endif; ?>
        </td>
        <td><span class="badge <?= $o['channel'] === 'whatsapp' ? 'badge-green' : 'badge-blue' ?>"><?= htmlspecialchars($o['channel']) ?></span></td>
        <td>
          <?= Currency::format((float)$o['total_idr']) ?>
          <?php if ($isLocal): ?>
            <br><small style="color:var(--text-light);font-size:.78rem">
              <?= Currency::format((float)$o['total_amount'], $o['branch_currency']) ?>
            </small>
          <?php endif; ?>
        </td>
        <td><span class="badge <?= match($o['order_status']) { 'completed' => 'badge-green', 'cancelled' => 'badge-red', default => 'badge-orange' } ?>">
          <?= htmlspecialchars($o['order_status']) ?>
        </span></td>
        <td><span class="badge <?= $o['payment_status'] === 'paid' ? 'badge-green' : 'badge-gray' ?>">
          <?= htmlspecialchars($o['payment_status']) ?>
        </span></td>
        <td style="white-space:nowrap;font-size:.8rem"><?= date('d/m/y H:i', strtotime($o['created_at'])) ?></td>
        <td><a href="<?= BASE_URL ?>/dashboard/super/order-detail.php?id=<?= $o['id'] ?>"
               class="btn btn-xs btn-outline" aria-label="Detail <?= htmlspecialchars($o['order_number']) ?>">Detail</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($orders)): ?>
        <tr><td colspan="9" style="text-align:center;color:var(--text-light)">
          <?= $hasFilter ? 'Tidak ada order yang cocok dengan filter.' : 'Belum ada order' ?>
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pagination" style="margin-top:16px">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
    <a href="<?= $paginateUrl . $p ?>" class="page-btn <?= $p == $page ? 'active' : '' ?>"
       aria-label="Halaman <?= $p ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
echo View::renderLayout('Semua Order', $content, 'super_admin');
