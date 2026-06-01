<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';
require_once dirname(__DIR__, 3) . '/plugins/woocommerce-connector/WooCommerceConnectorRepository.php';
require_once dirname(__DIR__, 3) . '/plugins/woocommerce-connector/WooCommerceConnectorClient.php';
require_once dirname(__DIR__, 3) . '/plugins/woocommerce-connector/WooCommerceConnectorService.php';

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

$service = new WooCommerceConnectorService();
$repo = $service->getRepository();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'sync_products') {
        $service->syncProductsSnapshot($branchId);
        $message = 'Snapshot katalog WooCommerce dicatat ke log sinkronisasi.';
    } elseif ($action === 'pull_products_live') {
        $result = $service->pullProductsLive($branchId);
        $message = (string)($result['message'] ?? 'Pull katalog selesai.');
    } elseif ($action === 'test_connection') {
        $result = $service->probe($branchId);
        $message = (string)($result['message'] ?? 'Tes koneksi selesai.');
    } elseif ($action === 'sync_orders') {
        $service->syncRecentOrdersSnapshot($branchId);
        $message = 'Snapshot order terbaru dicatat ke log sinkronisasi.';
    } elseif ($action === 'push_recent_orders_live') {
        $result = $service->pushRecentOrdersLive($branchId);
        $message = (string)($result['message'] ?? 'Push recent orders selesai.');
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
    <div class="card-title">WooCommerce Actions</div>
    <p style="color:var(--text-mid);margin-bottom:16px;line-height:1.7">
      Dari halaman ini Anda bisa uji koneksi, menarik katalog live dari WooCommerce, mendorong order terbaru, dan tetap menyimpan snapshot/log untuk audit integrasi.
    </p>
    <div class="button-row">
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="test_connection">
        <button type="submit" class="btn btn-outline">Test Connection</button>
      </form>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="pull_products_live">
        <button type="submit" class="btn btn-primary">Pull Live Products</button>
      </form>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="push_recent_orders_live">
        <button type="submit" class="btn btn-outline">Push Recent Orders</button>
      </form>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="sync_orders">
        <button type="submit" class="btn btn-outline">Queue Order Snapshot</button>
      </form>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="sync_products">
        <button type="submit" class="btn btn-outline">Queue Product Snapshot</button>
      </form>
    </div>
    <div style="margin-top:18px;background:var(--bg-light,#faf9f7);padding:14px;border-radius:10px;font-size:.86rem;line-height:1.7">
      <strong>Branch config</strong><br>
      REST API Base URL: <code><?= htmlspecialchars((string)$status['base_url']) ?></code><br>
      Store URL: <code><?= htmlspecialchars((string)$status['store_url']) ?></code><br>
      Flags:
      <code>orders=<?= $status['sync_orders'] ? '1' : '0' ?></code>,
      <code>products=<?= $status['sync_products'] ? '1' : '0' ?></code>,
      <code>live_order_push=<?= $status['live_order_push'] ? '1' : '0' ?></code>,
      <code>live_catalog_pull=<?= $status['live_catalog_pull'] ? '1' : '0' ?></code>
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
      <div style="color:var(--text-light)">Belum ada log sinkronisasi WooCommerce untuk cabang ini.</div>
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
      <a href="<?= BASE_URL ?>/dashboard/branch/settings.php" class="btn btn-outline btn-sm" style="margin-top:10px">Buka Pengaturan WooCommerce</a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::renderLayout('WooCommerce Connector', $content, 'branch_admin');
