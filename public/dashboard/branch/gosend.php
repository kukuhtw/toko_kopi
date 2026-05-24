<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';
require_once BASE_PATH . '/plugins/gosend-delivery/GoSendDeliveryRepository.php';
require_once BASE_PATH . '/plugins/gosend-delivery/GoSendDeliveryClient.php';
require_once BASE_PATH . '/plugins/gosend-delivery/GoSendDeliveryService.php';

use App\Helpers\{Auth, Csrf, View};

Auth::startSession();
Auth::requireLogin();

$user = Auth::user();
$branchId = (int)($user['branch_id'] ?? 0);
if (!$branchId && Auth::isSuperAdmin()) {
    $branchId = (int)($_GET['branch_id'] ?? 0);
}
if ($branchId <= 0) {
    exit('Branch not found');
}

$repo = new GoSendDeliveryRepository();
$service = new GoSendDeliveryService($repo);
$message = '';
$error = '';
$estimateResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'process_queue') {
            $result = $service->processPendingQueue($branchId, 20);
            $message = 'Queue GoSend diproses. Success: ' . ($result['success'] ?? 0) . ', Failed: ' . ($result['failed'] ?? 0) . '.';
        } elseif ($action === 'simulate_estimate') {
            $estimateResult = $service->estimate($branchId, [
                'origin_latitude' => $_POST['origin_latitude'] ?? $repo->getBranchSetting($branchId, 'origin_latitude', ''),
                'origin_longitude' => $_POST['origin_longitude'] ?? $repo->getBranchSetting($branchId, 'origin_longitude', ''),
                'destination_latitude' => $_POST['destination_latitude'] ?? '',
                'destination_longitude' => $_POST['destination_longitude'] ?? '',
            ]);
            $message = 'Simulasi estimasi GoSend selesai.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = $repo->getSummary($branchId);
$statuses = $repo->getBranchOrderStatuses($branchId, 30);
$logs = $repo->getRecentLogs($branchId, 20);
$audits = $repo->getWebhookAudits($branchId, 20);
$overview = $service->getClientOverview($branchId);
$runnerToken = $repo->getGlobalSetting('runner_token');
$runnerUrl = BASE_URL . '/api/plugins/gosend/process-queue.php?branch=' . $branchId . ($runnerToken !== '' ? '&token=' . rawurlencode($runnerToken) : '');
$webhookUrl = BASE_URL . '/api/plugins/gosend/webhook.php?branch=' . $branchId;

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="section-header">
  <h2>GoSend Delivery</h2>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <form method="POST" style="display:inline">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="process_queue">
      <button type="submit" class="btn btn-primary btn-sm">Proses Queue</button>
    </form>
  </div>
</div>

<div class="dashboard-grid-main-sidebar">
  <div>
    <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px">
      <div class="card"><div class="card-title">Total Log</div><div style="font-size:1.6rem;font-weight:800"><?= (int)($summary['total_logs'] ?? 0) ?></div></div>
      <div class="card"><div class="card-title">Success</div><div style="font-size:1.6rem;font-weight:800;color:var(--success)"><?= (int)($summary['success_logs'] ?? 0) ?></div></div>
      <div class="card"><div class="card-title">Pending</div><div style="font-size:1.6rem;font-weight:800;color:#9a6700"><?= (int)($summary['pending_logs'] ?? 0) + (int)($summary['retry_logs'] ?? 0) ?></div></div>
      <div class="card"><div class="card-title">Failed</div><div style="font-size:1.6rem;font-weight:800;color:var(--danger)"><?= (int)($summary['failed_logs'] ?? 0) ?></div></div>
    </div>

    <div class="card" style="margin-bottom:18px">
      <div class="card-title">Order Delivery Status</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Order</th><th>Status Delivery</th><th>External Ref</th><th>Tracking</th><th>Updated</th><th>Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($statuses as $row): ?>
          <tr>
            <td>#<?= htmlspecialchars((string)($row['order_number'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string)($row['delivery_status'] ?? 'queued')) ?></td>
            <td><?= htmlspecialchars((string)($row['external_ref'] ?? '-')) ?></td>
            <td>
              <?php if (!empty($row['tracking_url'])): ?>
                <a href="<?= htmlspecialchars((string)$row['tracking_url']) ?>" target="_blank" rel="noopener">Tracking</a>
              <?php else: ?>-<?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string)($row['updated_at'] ?? '-')) ?></td>
            <td><a href="<?= BASE_URL ?>/dashboard/branch/order-detail.php?id=<?= (int)($row['order_id'] ?? 0) ?>" class="btn btn-xs btn-outline">Detail</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$statuses): ?><tr><td colspan="6">Belum ada order GoSend.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card" style="margin-bottom:18px">
      <div class="card-title">Recent Queue Logs</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Waktu</th><th>Order</th><th>Event</th><th>Status</th><th>Error</th></tr></thead>
          <tbody>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td><?= htmlspecialchars((string)($log['created_at'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string)($log['order_number'] ?? '-')) ?></td>
            <td><?= htmlspecialchars((string)($log['event_name'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string)($log['status'] ?? 'pending')) ?></td>
            <td><?= htmlspecialchars((string)($log['last_error'] ?? '-')) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$logs): ?><tr><td colspan="5">Belum ada log GoSend.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Webhook Audit</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Waktu</th><th>Order</th><th>Remote Status</th><th>Status Order</th><th>Catatan</th></tr></thead>
          <tbody>
          <?php foreach ($audits as $audit): ?>
          <tr>
            <td><?= htmlspecialchars((string)($audit['created_at'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string)($audit['order_number'] ?? '-')) ?></td>
            <td><?= htmlspecialchars((string)($audit['remote_status'] ?? '-')) ?></td>
            <td><?= htmlspecialchars(trim((string)($audit['old_order_status'] ?? '-') . ' -> ' . (string)($audit['new_order_status'] ?? '-'))) ?></td>
            <td><?= htmlspecialchars((string)($audit['note'] ?? '-')) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$audits): ?><tr><td colspan="5">Belum ada audit webhook GoSend.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div>
    <div class="card" style="margin-bottom:18px">
      <div class="card-title">Runtime Overview</div>
      <div style="font-size:.84rem;line-height:1.8">
        Mode: <strong><?= htmlspecialchars((string)($overview['mode'] ?? 'mock')) ?></strong><br>
        Base URL: <code><?= htmlspecialchars((string)($overview['base_url'] ?? '')) ?></code><br>
        Client ID: <?= !empty($overview['has_client_id']) ? 'tersedia' : 'belum diisi' ?><br>
        Pass Key: <?= !empty($overview['has_pass_key']) ? 'tersedia' : 'belum diisi' ?><br>
        Service: <strong><?= htmlspecialchars((string)($overview['service_type'] ?? 'instant')) ?></strong><br>
        Auth: <code><?= htmlspecialchars((string)($overview['auth_mode'] ?? 'header_pair')) ?></code><br>
        Booking Path: <code><?= htmlspecialchars((string)($overview['booking_path'] ?? '')) ?></code><br>
        Pickup Path: <code><?= htmlspecialchars((string)($overview['pickup_path'] ?? '')) ?></code><br>
        Status Path: <code><?= htmlspecialchars((string)($overview['status_path'] ?? '')) ?></code>
      </div>
    </div>

    <div class="card" style="margin-bottom:18px">
      <div class="card-title">Endpoint</div>
      <div style="font-size:.82rem;line-height:1.7">
        Webhook:<br><code style="word-break:break-all"><?= htmlspecialchars($webhookUrl) ?></code><br><br>
        Queue Runner:<br><code style="word-break:break-all"><?= htmlspecialchars($runnerUrl) ?></code>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Simulasi Estimasi</div>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="simulate_estimate">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Dest Latitude</label>
            <input type="text" name="destination_latitude" class="form-control" placeholder="-6.2100">
          </div>
          <div class="form-group">
            <label class="form-label">Dest Longitude</label>
            <input type="text" name="destination_longitude" class="form-control" placeholder="106.8200">
          </div>
        </div>
        <button type="submit" class="btn btn-outline">Hitung Estimasi</button>
      </form>
      <?php if (is_array($estimateResult)): ?>
        <pre style="white-space:pre-wrap;font-size:.82rem;margin-top:12px"><?= htmlspecialchars((string)json_encode($estimateResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::renderLayout('GoSend Delivery', $content, Auth::isSuperAdmin() ? 'super_admin' : 'branch_admin');
