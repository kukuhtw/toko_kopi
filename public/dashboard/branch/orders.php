<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Currency, Csrf};
use App\Models\{OrderModel, BranchModel};

Auth::startSession();
Auth::requireLogin();

$user     = Auth::user();
$branchId = (int)$user['branch_id'];
if (!$branchId && !Auth::isSuperAdmin()) { http_response_code(403); exit; }

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $orderId = (int)$_POST['order_id'];
    $action  = $_POST['action'] ?? '';
    $orderModel = new OrderModel();

    $order = $orderModel->find($orderId);
    if (!$order || !Auth::canAccessBranch((int)$order['branch_id'])) {
        $error = 'Akses ditolak.';
    } else {
        if ($action === 'update_status') {
            $newStatus = $_POST['order_status'] ?? '';
            $allowed   = ['pending', 'processing', 'completed', 'cancelled'];
            if (!in_array($newStatus, $allowed, true)) {
                $error = "Status '{$newStatus}' tidak valid.";
            } else {
                try {
                    $orderModel->updateStatus($orderId, $newStatus, (int)$user['id']);
                    $message = 'Status order diperbarui menjadi ' . ucfirst($newStatus) . '.';
                } catch (\Throwable $e) {
                    $error = 'Gagal update status: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'mark_paid') {
            $orderModel->updatePayment($orderId, 'paid', (int)$user['id']);
            $message = 'Order ditandai sudah dibayar.';
        } elseif ($action === 'mark_unpaid') {
            $orderModel->updatePayment($orderId, 'unpaid', (int)$user['id']);
            $message = 'Status pembayaran diubah ke unpaid.';
        }
    }
}

// ── Filter params ─────────────────────────────────────────
$q       = trim($_GET['q']      ?? '');
$status  = $_GET['status']      ?? '';
$payment = $_GET['payment']     ?? '';
$period  = $_GET['period']      ?? '';

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

$filters   = array_filter(['q' => $q, 'status' => $status, 'payment' => $payment, 'date_from' => $dateFrom, 'date_to' => $dateTo]);
$hasFilter = $q !== '' || $status !== '' || $payment !== '' || $period !== '';

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$orderModel  = new OrderModel();
$currency    = (new BranchModel())->getCurrency($branchId);
$orders      = $orderModel->searchByBranch($branchId, $filters, $limit, $offset);
$total       = $orderModel->countSearchByBranch($branchId, $filters);
$pages       = (int)ceil($total / $limit);
$filterQuery = http_build_query(array_filter(['q' => $q, 'status' => $status, 'payment' => $payment, 'period' => $period]));
$paginateUrl = '?' . ($filterQuery ? $filterQuery . '&' : '') . 'page=';

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="section-header">
  <h2>Order Masuk</h2>
  <div class="section-actions">
    <span style="color:var(--text-light);font-size:.9rem">
      <?= number_format($total) ?> <?= $hasFilter ? 'hasil filter' : 'total' ?>
    </span>
    <a href="<?= BASE_URL ?>/api/export/orders-branch.php<?= $filterQuery ? '?' . $filterQuery : '' ?>"
       class="btn btn-outline" style="font-size:.85rem"><span aria-hidden="true">&#8595;</span> Export CSV</a>
  </div>
</div>

<!-- Filter Bar -->
<form method="GET" class="card" style="margin-bottom:16px;padding:14px 16px">
  <div class="filter-grid">
    <div class="filter-grow">
      <label for="f_q" style="font-size:.8rem;color:var(--text-mid);display:block;margin-bottom:4px">Cari order / customer</label>
      <input type="text" id="f_q" name="q" class="form-control compact-input"
             value="<?= htmlspecialchars($q) ?>" placeholder="No. order atau nama customer…">
    </div>
    <div class="filter-field">
      <label for="f_status" style="font-size:.8rem;color:var(--text-mid);display:block;margin-bottom:4px">Status</label>
      <select id="f_status" name="status" class="form-control compact-select">
        <option value="">Semua Status</option>
        <?php foreach (['pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $v => $l): ?>
        <option value="<?= $v ?>" <?= $status === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-field">
      <label for="f_payment" style="font-size:.8rem;color:var(--text-mid);display:block;margin-bottom:4px">Pembayaran</label>
      <select id="f_payment" name="payment" class="form-control compact-select">
        <option value="">Semua</option>
        <option value="paid"   <?= $payment === 'paid'   ? 'selected' : '' ?>>Paid</option>
        <option value="unpaid" <?= $payment === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
      </select>
    </div>
    <div class="filter-field">
      <label for="f_period" style="font-size:.8rem;color:var(--text-mid);display:block;margin-bottom:4px">Periode</label>
      <select id="f_period" name="period" class="form-control compact-select">
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
        <tr><th>No. Order</th><th>Customer</th><th>Channel</th><th>Total</th><th>Status Order</th><th>Payment</th><th>Waktu</th><th>Aksi</th></tr>
      </thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td><strong><a href="<?= BASE_URL ?>/dashboard/branch/order-detail.php?id=<?= $o['id'] ?>"
            aria-label="Detail order <?= htmlspecialchars($o['order_number']) ?>"><?= htmlspecialchars($o['order_number']) ?></a></strong></td>
        <td>
          <?= htmlspecialchars($o['customer_name']) ?>
          <?php if ($o['customer_wa']): ?><br><small>📱 <?= htmlspecialchars($o['customer_wa']) ?></small><?php endif; ?>
        </td>
        <td><span class="badge <?= $o['channel'] === 'whatsapp' ? 'badge-green' : 'badge-blue' ?>"><?= $o['channel'] ?></span></td>
        <td><?= Currency::format((float)$o['total_amount'], $currency) ?></td>
        <td>
          <form method="POST" style="display:flex;gap:6px;align-items:center">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
            <select name="order_status" class="form-control"
                    style="padding:4px 8px;font-size:.8rem;width:120px" onchange="this.form.submit()">
              <?php foreach (['pending', 'processing', 'completed', 'cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $o['order_status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td>
          <span class="badge <?= $o['payment_status'] === 'paid' ? 'badge-green' : 'badge-gray' ?>"
                style="margin-bottom:4px"><?= $o['payment_status'] ?></span>
          <?php if ($o['payment_status'] !== 'paid'): ?>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="mark_paid">
            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
            <button class="btn btn-xs btn-success" type="submit">Mark Paid</button>
          </form>
          <?php endif; ?>
        </td>
        <td style="font-size:.8rem;white-space:nowrap"><?= date('d/m H:i', strtotime($o['created_at'])) ?></td>
        <td><a href="<?= BASE_URL ?>/dashboard/branch/order-detail.php?id=<?= $o['id'] ?>" class="btn btn-xs btn-outline">Detail</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($orders)): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--text-light)">
          <?= $hasFilter ? 'Tidak ada order yang cocok dengan filter.' : 'Belum ada order' ?>
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
    <a href="<?= $paginateUrl . $p ?>" class="page-btn <?= $p == $page ? 'active' : '' ?>" aria-label="Halaman <?= $p ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
echo View::renderLayout('Order Masuk', $content, 'branch_admin');
