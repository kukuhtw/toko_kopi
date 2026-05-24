<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';
require_once BASE_PATH . '/plugins/gosend-delivery/GoSendDeliveryRepository.php';

use App\Helpers\{Auth, View};

Auth::startSession();
Auth::requireRole('super_admin');

$repo = new GoSendDeliveryRepository();
$summary = $repo->getSummary();
$branches = $repo->getBranchStatuses();
$logs = $repo->getRecentLogs(null, 30);

ob_start();
?>
<div class="section-header">
  <h2>GoSend Delivery</h2>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/dashboard/super/settings.php" class="btn btn-outline btn-sm">Buka Settings GoSend</a>
    <a href="<?= BASE_URL ?>/docs/gosend-delivery.php" class="btn btn-outline btn-sm">Buka Docs GoSend</a>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px">
  <div class="card"><div class="card-title">Total Log</div><div style="font-size:1.6rem;font-weight:800"><?= (int)($summary['total_logs'] ?? 0) ?></div></div>
  <div class="card"><div class="card-title">Success</div><div style="font-size:1.6rem;font-weight:800;color:var(--success)"><?= (int)($summary['success_logs'] ?? 0) ?></div></div>
  <div class="card"><div class="card-title">Pending</div><div style="font-size:1.6rem;font-weight:800;color:#9a6700"><?= (int)($summary['pending_logs'] ?? 0) + (int)($summary['retry_logs'] ?? 0) ?></div></div>
  <div class="card"><div class="card-title">Failed</div><div style="font-size:1.6rem;font-weight:800;color:var(--danger)"><?= (int)($summary['failed_logs'] ?? 0) ?></div></div>
</div>

<div class="dashboard-grid-main-sidebar">
  <div class="card">
    <div class="card-title">Status per Cabang</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Cabang</th><th>Aktif</th><th>Service</th><th>Total Delivery</th><th>Open Delivery</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($branches as $branch): ?>
        <tr>
          <td><?= htmlspecialchars((string)($branch['name'] ?? '')) ?></td>
          <td><?= (string)($branch['is_active'] ?? '0') === '1' ? 'Ya' : 'Tidak' ?></td>
          <td><?= htmlspecialchars((string)($branch['service_type'] ?? 'instant')) ?></td>
          <td><?= (int)($branch['total_delivery_orders'] ?? 0) ?></td>
          <td><?= (int)($branch['open_delivery_orders'] ?? 0) ?></td>
          <td><a href="<?= BASE_URL ?>/dashboard/branch/gosend.php?branch_id=<?= (int)$branch['id'] ?>" class="btn btn-xs btn-outline">Buka</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Recent Logs</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Waktu</th><th>Cabang</th><th>Order</th><th>Event</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td><?= htmlspecialchars((string)($log['created_at'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($log['branch_name'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($log['order_number'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string)($log['event_name'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($log['status'] ?? 'pending')) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="5">Belum ada log GoSend.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::renderLayout('GoSend Delivery', $content, 'super_admin');
