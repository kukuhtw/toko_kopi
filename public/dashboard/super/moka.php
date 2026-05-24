<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';
require_once dirname(__DIR__, 3) . '/plugins/moka-connect-private-solution/MokaConnectRepository.php';
require_once dirname(__DIR__, 3) . '/plugins/moka-connect-private-solution/MokaConnectClient.php';
require_once dirname(__DIR__, 3) . '/plugins/moka-connect-private-solution/MokaConnectService.php';

use App\Helpers\{Auth, View};

Auth::startSession();
Auth::requireRole('super_admin');

$repo = (new MokaConnectService())->getRepository();
$summary = $repo->getSummary();
$branchStatuses = $repo->getBranchStatuses();
$logs = $repo->getRecentLogs(null, 40);

$badgeClass = static function (string $value): string {
    return match ($value) {
        'success' => 'badge-success',
        'failed', 'config_missing' => 'badge-danger',
        default => 'badge-warning',
    };
};

ob_start();
?>
<div class="dashboard-grid-3" style="margin-bottom:24px">
  <div class="card">
    <div class="card-title">Total Logs</div>
    <div style="font-size:1.5rem;font-weight:700"><?= (int)($summary['total_logs'] ?? 0) ?></div>
  </div>
  <div class="card">
    <div class="card-title">Pending / Retry</div>
    <div style="font-size:1.5rem;font-weight:700"><?= (int)($summary['pending_logs'] ?? 0) ?> / <?= (int)($summary['retry_logs'] ?? 0) ?></div>
  </div>
  <div class="card">
    <div class="card-title">Last Activity</div>
    <div style="font-size:1.05rem;font-weight:700"><?= htmlspecialchars((string)($summary['last_activity'] ?? '-')) ?></div>
  </div>
</div>

<div class="dashboard-grid-main-aside">
  <div class="card">
    <div class="card-title">Branch Status</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Cabang</th>
            <th>Active</th>
            <th>Merchant ID</th>
            <th>Outlet ID</th>
            <th>Total Logs</th>
            <th>Retry</th>
            <th>Failed</th>
            <th>Last Activity</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($branchStatuses as $row): ?>
          <tr>
            <td><?= htmlspecialchars((string)$row['name']) ?></td>
            <td><?= ($row['is_active'] ?? '0') === '1' ? 'Yes' : 'No' ?></td>
            <td><?= htmlspecialchars((string)($row['merchant_id'] ?? '-')) ?></td>
            <td><?= htmlspecialchars((string)($row['outlet_id'] ?? '-')) ?></td>
            <td><?= (int)($row['total_logs'] ?? 0) ?></td>
            <td><?= (int)($row['retry_logs'] ?? 0) ?></td>
            <td><?= (int)($row['failed_logs'] ?? 0) ?></td>
            <td><?= htmlspecialchars((string)($row['last_activity'] ?? '-')) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if ($branchStatuses === []): ?>
          <tr><td colspan="8" style="text-align:center;color:var(--text-light)">Belum ada data cabang.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Recent Activity</div>
    <div style="display:flex;flex-direction:column;gap:10px;max-height:720px;overflow:auto">
      <?php foreach ($logs as $log): ?>
      <div style="border:1px solid var(--border);border-radius:10px;padding:12px">
        <div style="display:flex;justify-content:space-between;gap:10px;align-items:center">
          <strong><?= htmlspecialchars((string)($log['branch_name'] ?? ('Branch #' . $log['branch_id']))) ?></strong>
          <span class="badge <?= $badgeClass((string)$log['status']) ?>">
            <?= htmlspecialchars((string)$log['status']) ?>
          </span>
        </div>
        <div style="font-size:.82rem;color:var(--text-light);margin-top:6px">
          <?= htmlspecialchars((string)$log['entity_type']) ?> / <?= htmlspecialchars((string)$log['event_name']) ?>
          | Ref <?= htmlspecialchars((string)($log['reference_id'] ?? '-')) ?>
          | <?= htmlspecialchars((string)$log['created_at']) ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if ($logs === []): ?>
      <div style="color:var(--text-light)">Belum ada activity log Moka.</div>
      <?php endif; ?>
    </div>
    <a href="<?= BASE_URL ?>/dashboard/super/settings.php" class="btn btn-outline btn-sm" style="margin-top:14px">Kelola Pengaturan Moka</a>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::renderLayout('Moka Connect', $content, 'super_admin');
