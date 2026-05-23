<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';
require_once dirname(__DIR__, 3) . '/plugins/sirclo-full-connector/SircloConnectorRepository.php';
require_once dirname(__DIR__, 3) . '/plugins/sirclo-full-connector/SircloConnectorService.php';

use App\Helpers\{Auth, View, Csrf};
use App\Models\BranchModel;

Auth::startSession();
Auth::requireLogin();

$user = Auth::user();
$branchId = (int)($user['branch_id'] ?? 0);
if ($branchId <= 0) {
    header('Location: ' . BASE_URL . '/dashboard/super/');
    exit;
}

$service = new SircloConnectorService();
$repo = $service->getRepository();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'sync_products') {
        $service->syncProductsSnapshot($branchId);
        $message = 'Snapshot produk Sirclo dicatat ke log sinkronisasi.';
    } elseif ($action === 'sync_customers') {
        $service->syncCustomersSnapshot($branchId);
        $message = 'Snapshot customer Sirclo dicatat ke log sinkronisasi.';
    } elseif ($action === 'sync_orders') {
        $service->syncRecentOrdersSnapshot($branchId);
        $message = 'Snapshot order terbaru dicatat ke log sinkronisasi.';
    }
}

$status = $service->getConnectionStatus($branchId);
$summary = $repo->getSummary($branchId);
$logs = $repo->getRecentLogs($branchId, 25);
$branchName = (new BranchModel())->find($branchId)['name'] ?? ('Branch #' . $branchId);

$fmtJson = static function (?string $json): string {
    if ($json === null || $json === '') {
        return '-';
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return $json;
    }

    return (string)json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

ob_start();
?>
<?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="dashboard-grid-3" style="margin-bottom:24px">
  <div class="card">
    <div class="card-title">Connection</div>
    <div style="font-size:1.4rem;font-weight:700"><?= $status['enabled'] ? 'Enabled' : 'Disabled' ?></div>
    <div style="color:var(--text-light);margin-top:6px"><?= htmlspecialchars($branchName) ?></div>
  </div>
  <div class="card">
    <div class="card-title">Credentials</div>
    <div style="font-size:1.4rem;font-weight:700"><?= $status['has_credentials'] ? 'Ready' : 'Incomplete' ?></div>
    <div style="color:var(--text-light);margin-top:6px">Mode <?= htmlspecialchars(strtoupper((string)$status['mode'])) ?></div>
  </div>
  <div class="card">
    <div class="card-title">Last Activity</div>
    <div style="font-size:1.1rem;font-weight:700"><?= htmlspecialchars((string)($summary['last_activity'] ?? '-')) ?></div>
    <div style="color:var(--text-light);margin-top:6px"><?= (int)($summary['total_logs'] ?? 0) ?> total logs</div>
  </div>
</div>

<div class="dashboard-grid-main-sidebar">
  <div class="card">
    <div class="card-title">Manual Sync Queue</div>
    <p style="color:var(--text-mid);margin-bottom:16px;line-height:1.7">
      Tombol di bawah ini belum mengirim request HTTP ke Sirclo. Saat ini plugin menyiapkan snapshot data dan mencatatnya ke log sinkronisasi, sehingga kita punya fondasi aman untuk menyambungkan API Sirclo berikutnya.
    </p>
    <div class="button-row">
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="sync_orders">
        <button type="submit" class="btn btn-primary">Queue Order Sync</button>
      </form>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="sync_products">
        <button type="submit" class="btn btn-outline">Queue Product Sync</button>
      </form>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="sync_customers">
        <button type="submit" class="btn btn-outline">Queue Customer Sync</button>
      </form>
    </div>
    <div style="margin-top:18px;background:var(--bg-light,#faf9f7);padding:14px;border-radius:10px;font-size:.86rem;line-height:1.7">
      <strong>Branch config</strong><br>
      API Base URL: <code><?= htmlspecialchars((string)$status['base_url']) ?></code><br>
      Store ID: <code><?= htmlspecialchars((string)$status['store_id']) ?></code><br>
      Flags:
      <code>orders=<?= $status['sync_orders'] ? '1' : '0' ?></code>,
      <code>products=<?= $status['sync_products'] ? '1' : '0' ?></code>,
      <code>customers=<?= $status['sync_customers'] ? '1' : '0' ?></code>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Recent Sync Logs</div>
    <div style="display:flex;flex-direction:column;gap:12px;max-height:720px;overflow:auto">
      <?php foreach ($logs as $log): ?>
      <div style="border:1px solid var(--border);border-radius:10px;padding:12px">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:6px">
          <strong><?= htmlspecialchars((string)$log['entity_type']) ?> / <?= htmlspecialchars((string)$log['event_name']) ?></strong>
          <span class="badge <?= in_array($log['status'], ['failed', 'config_missing'], true) ? 'badge-danger' : ($log['status'] === 'success' ? 'badge-success' : 'badge-warning') ?>">
            <?= htmlspecialchars((string)$log['status']) ?>
          </span>
        </div>
        <div style="font-size:.82rem;color:var(--text-light);margin-bottom:8px">
          Ref: <?= htmlspecialchars((string)($log['reference_id'] ?? '-')) ?> · <?= htmlspecialchars((string)$log['created_at']) ?>
        </div>
        <details>
          <summary style="cursor:pointer;color:var(--coffee-brown)">Lihat payload / response</summary>
          <pre style="white-space:pre-wrap;background:#f8fafc;padding:10px;border-radius:8px;overflow:auto;font-size:.76rem;margin-top:8px"><?= htmlspecialchars($fmtJson($log['payload_preview'] ?? null)) ?></pre>
          <pre style="white-space:pre-wrap;background:#f8fafc;padding:10px;border-radius:8px;overflow:auto;font-size:.76rem;margin-top:8px"><?= htmlspecialchars($fmtJson($log['response_preview'] ?? null)) ?></pre>
        </details>
      </div>
      <?php endforeach; ?>
      <?php if ($logs === []): ?>
      <div style="color:var(--text-light)">Belum ada log sinkronisasi Sirclo untuk cabang ini.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Summary</div>
    <div style="display:flex;flex-direction:column;gap:10px">
      <div style="display:flex;justify-content:space-between"><span>Total logs</span><strong><?= (int)($summary['total_logs'] ?? 0) ?></strong></div>
      <div style="display:flex;justify-content:space-between"><span>Pending</span><strong><?= (int)($summary['pending_logs'] ?? 0) ?></strong></div>
      <div style="display:flex;justify-content:space-between"><span>Success</span><strong><?= (int)($summary['success_logs'] ?? 0) ?></strong></div>
      <div style="display:flex;justify-content:space-between"><span>Failed/config</span><strong><?= (int)($summary['failed_logs'] ?? 0) ?></strong></div>
      <a href="<?= BASE_URL ?>/dashboard/branch/settings.php" class="btn btn-outline btn-sm" style="margin-top:10px">Buka Pengaturan Sirclo</a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::renderLayout('Sirclo Connector', $content, 'branch_admin');
