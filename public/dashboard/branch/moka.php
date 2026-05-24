<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';
require_once dirname(__DIR__, 3) . '/plugins/moka-connect-private-solution/MokaConnectRepository.php';
require_once dirname(__DIR__, 3) . '/plugins/moka-connect-private-solution/MokaConnectClient.php';
require_once dirname(__DIR__, 3) . '/plugins/moka-connect-private-solution/MokaConnectService.php';

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

$service = new MokaConnectService();
$repo = $service->getRepository();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'sync_products') {
        $service->syncProductsSnapshot($branchId);
        $message = 'Snapshot katalog produk Moka dicatat ke log sinkronisasi.';
    } elseif ($action === 'sync_customers') {
        $service->syncCustomersSnapshot($branchId);
        $message = 'Snapshot customer Moka dicatat ke log sinkronisasi.';
    } elseif ($action === 'sync_orders') {
        $service->syncRecentOrdersSnapshot($branchId);
        $message = 'Snapshot payload order terbaru dicatat ke log sinkronisasi.';
    } elseif ($action === 'sync_outlets') {
        $service->syncOutletsSnapshot($branchId);
        $message = 'Snapshot outlet Moka dicatat ke log sinkronisasi.';
    } elseif ($action === 'test_connection') {
        $result = $service->testConnection($branchId);
        $message = (string)($result['message'] ?? 'Tes koneksi selesai.');
        $messageType = !empty($result['success']) ? 'success' : 'error';
    } elseif ($action === 'process_pending') {
        $result = $service->processPendingQueue($branchId, 10);
        $message = (string)($result['message'] ?? 'Queue diproses.');
    } elseif ($action === 'retry_failed') {
        $result = $service->retryFailedQueue($branchId, 10);
        $message = (string)($result['message'] ?? 'Retry queue diproses.');
    } elseif ($action === 'pull_live_products') {
        $result = $service->pullProductsLive($branchId);
        $message = (string)($result['message'] ?? 'Live catalog pull selesai.');
        $messageType = !empty($result['success']) ? 'success' : 'error';
    } elseif ($action === 'resend_order') {
        $result = $service->resendOrder($branchId, (int)($_POST['order_id'] ?? 0));
        $message = (string)($result['message'] ?? 'Order dimasukkan ulang ke queue.');
        $messageType = !empty($result['success']) ? 'success' : 'error';
    } elseif ($action === 'push_log') {
        $result = $service->pushQueuedLog((int)($_POST['log_id'] ?? 0));
        $message = (string)($result['message'] ?? 'Queue diproses.');
        $messageType = !empty($result['success']) ? 'success' : 'error';
    }
}

$status = $service->getConnectionStatus($branchId);
$summary = $repo->getSummary($branchId);
$logs = $repo->getRecentLogs($branchId, 25);
$orderStatuses = $repo->getRecentOrderStatuses($branchId, 15);
$mappingPreview = $service->getMappingPreview($branchId);
$audits = $repo->getRecentWebhookAudits($branchId, 8);
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

$badgeClass = static function (string $value): string {
    return match ($value) {
        'success' => 'badge-success',
        'failed', 'config_missing' => 'badge-danger',
        'retry_scheduled' => 'badge-warning',
        default => 'badge-warning',
    };
};

ob_start();
?>
<?php if ($message !== ''): ?><div class="alert alert-<?= $messageType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="dashboard-grid-3" style="margin-bottom:24px">
  <div class="card">
    <div class="card-title">Connection</div>
    <div style="font-size:1.4rem;font-weight:700"><?= $status['enabled'] ? 'Enabled' : 'Disabled' ?></div>
    <div style="color:var(--text-light);margin-top:6px"><?= htmlspecialchars($branchName) ?></div>
  </div>
  <div class="card">
    <div class="card-title">Credentials</div>
    <div style="font-size:1.4rem;font-weight:700"><?= $status['has_credentials'] ? 'Ready' : 'Incomplete' ?></div>
    <div style="color:var(--text-light);margin-top:6px"><?= htmlspecialchars(strtoupper((string)$status['auth_mode'])) ?></div>
  </div>
  <div class="card">
    <div class="card-title">Live Mode</div>
    <div style="font-size:1.1rem;font-weight:700">
      Order <?= $status['live_order_push'] ? 'ON' : 'OFF' ?> / Catalog <?= $status['live_catalog_pull'] ? 'ON' : 'OFF' ?>
    </div>
    <div style="color:var(--text-light);margin-top:6px">Retry max <?= (int)$status['max_retries'] ?> kali, delay <?= (int)$status['retry_delay_seconds'] ?> detik</div>
  </div>
</div>

<div class="dashboard-grid-main-sidebar">
  <div class="card">
    <div class="card-title">Live Sync Controls</div>
    <p style="color:var(--text-mid);margin-bottom:16px;line-height:1.7">
      Dari sini Anda bisa uji koneksi Moka, memproses queue order yang tertunda, retry yang gagal, serta menarik katalog live dari endpoint Moka. Snapshot manual tetap tersedia untuk review payload dan field mapping.
    </p>
    <div class="button-row">
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="test_connection">
        <button type="submit" class="btn btn-primary">Test Connection</button>
      </form>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="process_pending">
        <button type="submit" class="btn btn-outline">Push Pending Orders</button>
      </form>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="retry_failed">
        <button type="submit" class="btn btn-outline">Retry Failed Orders</button>
      </form>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="pull_live_products">
        <button type="submit" class="btn btn-outline">Pull Live Catalog</button>
      </form>
    </div>
    <div class="button-row" style="margin-top:10px">
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
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="sync_customers">
        <button type="submit" class="btn btn-outline">Queue Customer Snapshot</button>
      </form>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="sync_outlets">
        <button type="submit" class="btn btn-outline">Queue Outlet Snapshot</button>
      </form>
    </div>
    <div style="margin-top:18px;background:var(--bg-light,#faf9f7);padding:14px;border-radius:10px;font-size:.86rem;line-height:1.7">
      <strong>Branch config</strong><br>
      API Base URL: <code><?= htmlspecialchars((string)$status['base_url']) ?></code><br>
      Merchant ID: <code><?= htmlspecialchars((string)$status['merchant_id']) ?></code><br>
      Outlet ID: <code><?= htmlspecialchars((string)$status['outlet_id']) ?></code><br>
      Flags:
      <code>orders=<?= $status['sync_orders'] ? '1' : '0' ?></code>,
      <code>products=<?= $status['sync_products'] ? '1' : '0' ?></code>,
      <code>customers=<?= $status['sync_customers'] ? '1' : '0' ?></code>,
      <code>outlets=<?= $status['sync_outlets'] ? '1' : '0' ?></code>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Recent Sync Logs</div>
    <div style="display:flex;flex-direction:column;gap:12px;max-height:780px;overflow:auto">
      <?php foreach ($logs as $log): ?>
      <div style="border:1px solid var(--border);border-radius:10px;padding:12px">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:6px">
          <strong><?= htmlspecialchars((string)$log['entity_type']) ?> / <?= htmlspecialchars((string)$log['event_name']) ?></strong>
          <span class="badge <?= $badgeClass((string)$log['status']) ?>">
            <?= htmlspecialchars((string)$log['status']) ?>
          </span>
        </div>
        <div style="font-size:.82rem;color:var(--text-light);margin-bottom:8px">
          Ref: <?= htmlspecialchars((string)($log['reference_id'] ?? '-')) ?> | Attempt <?= (int)($log['attempt_count'] ?? 0) ?> | <?= htmlspecialchars((string)$log['created_at']) ?>
        </div>
        <?php if (!empty($log['order_id'])): ?>
        <div style="font-size:.82rem;color:var(--text-light);margin-bottom:8px">
          Order: <strong><?= htmlspecialchars((string)($log['order_number'] ?? ('#' . $log['order_id']))) ?></strong>
          <?php if (!empty($log['http_status'])): ?> | HTTP <?= (int)$log['http_status'] ?><?php endif; ?>
          <?php if (!empty($log['next_retry_at'])): ?> | Retry at <?= htmlspecialchars((string)$log['next_retry_at']) ?><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($log['last_error'])): ?>
        <div style="background:#fff5f5;color:#9b1c1c;padding:10px;border-radius:8px;font-size:.82rem;margin-bottom:8px">
          <?= htmlspecialchars((string)$log['last_error']) ?>
        </div>
        <?php endif; ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
          <?php if (!empty($log['order_id'])): ?>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="resend_order">
            <input type="hidden" name="order_id" value="<?= (int)$log['order_id'] ?>">
            <button type="submit" class="btn btn-outline btn-sm">Re-send Order</button>
          </form>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="push_log">
            <input type="hidden" name="log_id" value="<?= (int)$log['id'] ?>">
            <button type="submit" class="btn btn-outline btn-sm">Push Log Ini</button>
          </form>
          <?php endif; ?>
        </div>
        <details>
          <summary style="cursor:pointer;color:var(--coffee-brown)">Lihat payload / response</summary>
          <pre style="white-space:pre-wrap;background:#f8fafc;padding:10px;border-radius:8px;overflow:auto;font-size:.76rem;margin-top:8px"><?= htmlspecialchars($fmtJson($log['payload_preview'] ?? null)) ?></pre>
          <pre style="white-space:pre-wrap;background:#f8fafc;padding:10px;border-radius:8px;overflow:auto;font-size:.76rem;margin-top:8px"><?= htmlspecialchars($fmtJson($log['response_preview'] ?? null)) ?></pre>
        </details>
      </div>
      <?php endforeach; ?>
      <?php if ($logs === []): ?>
      <div style="color:var(--text-light)">Belum ada log sinkronisasi Moka untuk cabang ini.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Summary</div>
    <div style="display:flex;flex-direction:column;gap:10px">
      <div style="display:flex;justify-content:space-between"><span>Total logs</span><strong><?= (int)($summary['total_logs'] ?? 0) ?></strong></div>
      <div style="display:flex;justify-content:space-between"><span>Pending</span><strong><?= (int)($summary['pending_logs'] ?? 0) ?></strong></div>
      <div style="display:flex;justify-content:space-between"><span>Retry scheduled</span><strong><?= (int)($summary['retry_logs'] ?? 0) ?></strong></div>
      <div style="display:flex;justify-content:space-between"><span>Success</span><strong><?= (int)($summary['success_logs'] ?? 0) ?></strong></div>
      <div style="display:flex;justify-content:space-between"><span>Failed/config</span><strong><?= (int)($summary['failed_logs'] ?? 0) ?></strong></div>
      <a href="<?= BASE_URL ?>/dashboard/branch/settings.php" class="btn btn-outline btn-sm" style="margin-top:10px">Buka Pengaturan Moka</a>
      <a href="<?= BASE_URL ?>/dashboard/branch/moka-webhook-test.php" class="btn btn-outline btn-sm">Test Webhook & Mapping</a>
    </div>
  </div>
</div>

<div class="dashboard-grid-main-sidebar" style="margin-top:20px">
  <div class="card">
    <div class="card-title">Status Per Order</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Order</th>
            <th>Sync Status</th>
            <th>Attempt</th>
            <th>Order Status</th>
            <th>Payment</th>
            <th>Last Synced</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orderStatuses as $row): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars((string)$row['order_number']) ?></strong><br>
              <small style="color:var(--text-light)">Rp<?= number_format((float)($row['total_amount'] ?? 0), 0, ',', '.') ?></small>
            </td>
            <td><span class="badge <?= $badgeClass((string)$row['last_status']) ?>"><?= htmlspecialchars((string)$row['last_status']) ?></span></td>
            <td><?= (int)($row['last_attempt_count'] ?? 0) ?></td>
            <td><?= htmlspecialchars((string)($row['order_status'] ?? '-')) ?></td>
            <td><?= htmlspecialchars((string)($row['payment_status'] ?? '-')) ?></td>
            <td><?= htmlspecialchars((string)($row['last_synced_at'] ?? '-')) ?></td>
            <td>
              <form method="POST">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="resend_order">
                <input type="hidden" name="order_id" value="<?= (int)$row['order_id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm">Re-send</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if ($orderStatuses === []): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--text-light)">Belum ada status sinkronisasi order Moka.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Field Mapping</div>
    <div style="display:grid;gap:16px">
      <div>
        <div style="font-weight:700;margin-bottom:8px">Order -> Moka</div>
        <div style="display:flex;flex-direction:column;gap:6px;font-size:.86rem;color:var(--text-mid)">
          <?php foreach ($mappingPreview['order'] as $line): ?>
          <div><?= htmlspecialchars((string)$line) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <div style="font-weight:700;margin-bottom:8px">Catalog -> Internal Review</div>
        <div style="display:flex;flex-direction:column;gap:6px;font-size:.86rem;color:var(--text-mid)">
          <?php foreach ($mappingPreview['catalog'] as $line): ?>
          <div><?= htmlspecialchars((string)$line) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Webhook Audit Ringkas</div>
    <div style="display:flex;flex-direction:column;gap:10px">
      <?php foreach ($audits as $audit): ?>
      <div style="border:1px solid var(--border);border-radius:10px;padding:10px">
        <div style="display:flex;justify-content:space-between;gap:10px;align-items:center">
          <strong><?= htmlspecialchars((string)($audit['order_number'] ?? '-')) ?></strong>
          <span class="badge <?= ($audit['changed_fields'] ?? '') !== '' ? 'badge-success' : 'badge-warning' ?>">
            <?= htmlspecialchars((string)($audit['source_type'] ?? 'webhook')) ?>
          </span>
        </div>
        <div style="font-size:.8rem;color:var(--text-light);margin-top:6px">
          <?= htmlspecialchars((string)$audit['created_at']) ?>
          <?php if (!empty($audit['changed_fields'])): ?> | <?= htmlspecialchars((string)$audit['changed_fields']) ?><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if ($audits === []): ?>
      <div style="color:var(--text-light)">Belum ada audit webhook.</div>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/dashboard/branch/moka-webhook-test.php" class="btn btn-outline btn-sm" style="margin-top:6px">Lihat Audit Lengkap</a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::renderLayout('Moka Connect', $content, 'branch_admin');
