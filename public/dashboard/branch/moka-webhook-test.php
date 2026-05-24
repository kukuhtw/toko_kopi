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

$repo = new MokaConnectRepository();
$service = new MokaConnectService($repo);
$branch = (new BranchModel())->find($branchId);
$branchName = (string)($branch['name'] ?? ('Branch #' . $branchId));
$message = '';
$messageType = 'success';
$simulationPayload = "{\n  \"order\": {\n    \"external_order_id\": \"ORD-20260524-ABC123\",\n    \"status\": \"closed\",\n    \"payment\": {\n      \"status\": \"paid\"\n    }\n  },\n  \"id\": \"moka-order-001\"\n}";
$simulationResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'simulate_webhook') {
        $simulationPayload = trim((string)($_POST['payload_json'] ?? ''));
        $decoded = json_decode($simulationPayload, true);
        if (!is_array($decoded)) {
            $message = 'Payload JSON tidak valid.';
            $messageType = 'error';
        } else {
            $simulationResult = $service->handleInboundWebhook($branchId, $decoded, 'simulation');
            $message = (string)($simulationResult['message'] ?? 'Simulasi selesai.');
            $messageType = !empty($simulationResult['success']) ? 'success' : 'error';
        }
    } elseif ($action === 'import_mapping_json') {
        $raw = trim((string)($_POST['mapping_json'] ?? ''));
        if ($raw === '' && isset($_FILES['mapping_file']) && (int)($_FILES['mapping_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $raw = (string)file_get_contents((string)$_FILES['mapping_file']['tmp_name']);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $message = 'File atau teks import mapping harus berupa JSON object.';
            $messageType = 'error';
        } else {
            $settings = [];
            foreach (MokaConnectRepository::MAPPING_KEYS as $key) {
                if (array_key_exists($key, $decoded)) {
                    $settings[$key] = (string)$decoded[$key];
                }
            }

            if ($settings === []) {
                $message = 'Tidak ada key mapping Moka yang valid di JSON import.';
                $messageType = 'error';
            } else {
                $repo->saveBranchSettings($branchId, $settings);
                $message = 'Mapping config cabang berhasil diimport.';
            }
        }
    }
}

if (isset($_GET['download']) && $_GET['download'] === 'mapping') {
    $export = array_merge(
        [
            'plugin_slug' => MokaConnectRepository::PLUGIN_SLUG,
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'exported_at' => date('c'),
        ],
        $repo->getMappingConfig($branchId)
    );

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="moka-mapping-branch-' . $branchId . '.json"');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$mappingConfig = $repo->getMappingConfig($branchId);
$mappingJson = (string)json_encode($mappingConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$audits = $repo->getRecentWebhookAudits($branchId, 30);

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
<?php if ($message !== ''): ?><div class="alert alert-<?= $messageType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:20px">
  <div class="card-title">Webhook Simulation</div>
  <p style="color:var(--text-mid);line-height:1.7;margin-bottom:16px">
    Halaman ini dipakai untuk menguji payload inbound Moka ke cabang <strong><?= htmlspecialchars($branchName) ?></strong> tanpa perlu menunggu webhook live. Mapping inbound yang aktif akan dipakai sama persis seperti endpoint produksi.
  </p>
  <form method="POST">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="simulate_webhook">
    <div class="form-group">
      <label class="form-label" for="payload_json">Payload JSON</label>
      <textarea id="payload_json" name="payload_json" class="form-control" rows="16" style="font-family:Consolas,monospace"><?= htmlspecialchars($simulationPayload) ?></textarea>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary">Jalankan Simulasi</button>
      <a href="<?= BASE_URL ?>/dashboard/branch/moka.php" class="btn btn-outline">Kembali ke Dashboard Moka</a>
    </div>
  </form>

  <?php if (is_array($simulationResult)): ?>
  <div style="margin-top:16px;background:var(--bg-light,#faf9f7);border-radius:10px;padding:14px">
    <div style="font-weight:700;margin-bottom:8px">Hasil Simulasi</div>
    <pre style="white-space:pre-wrap;font-size:.82rem"><?= htmlspecialchars((string)json_encode($simulationResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
  </div>
  <?php endif; ?>
</div>

<div class="dashboard-grid-main-sidebar">
  <div class="card">
    <div class="card-title">Export / Import Mapping</div>
    <p style="color:var(--text-mid);line-height:1.7;margin-bottom:16px">
      Export menghasilkan JSON config mapping per cabang. Import menerima JSON yang berisi key mapping Moka dan akan menimpa nilai mapping aktif cabang ini.
    </p>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
      <a href="<?= BASE_URL ?>/dashboard/branch/moka-webhook-test.php?download=mapping" class="btn btn-outline">Export Mapping JSON</a>
      <a href="<?= BASE_URL ?>/dashboard/branch/settings.php#plugin-moka-connect-private-solution" class="btn btn-outline">Buka Settings Mapping</a>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="import_mapping_json">
      <div class="form-group">
        <label class="form-label" for="mapping_file">Upload JSON</label>
        <input type="file" id="mapping_file" name="mapping_file" class="form-control" accept=".json,application/json">
      </div>
      <div class="form-group">
        <label class="form-label" for="mapping_json">Atau tempel JSON mapping</label>
        <textarea id="mapping_json" name="mapping_json" class="form-control" rows="16" style="font-family:Consolas,monospace"><?= htmlspecialchars($mappingJson) ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Import Mapping</button>
    </form>
  </div>

  <div class="card">
    <div class="card-title">Webhook Audit Trail</div>
    <div style="display:flex;flex-direction:column;gap:12px;max-height:920px;overflow:auto">
      <?php foreach ($audits as $audit): ?>
      <div style="border:1px solid var(--border);border-radius:10px;padding:12px">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center">
          <strong><?= htmlspecialchars((string)($audit['order_number'] ?? '-')) ?></strong>
          <span class="badge <?= ($audit['changed_fields'] ?? '') !== '' ? 'badge-success' : 'badge-warning' ?>">
            <?= htmlspecialchars((string)($audit['source_type'] ?? 'webhook')) ?>
          </span>
        </div>
        <div style="font-size:.82rem;color:var(--text-light);margin-top:6px">
          <?= htmlspecialchars((string)$audit['created_at']) ?>
          <?php if (!empty($audit['changed_fields'])): ?> | Changed: <?= htmlspecialchars((string)$audit['changed_fields']) ?><?php endif; ?>
        </div>
        <div style="margin-top:10px;font-size:.86rem;line-height:1.7">
          Remote order/payment: <code><?= htmlspecialchars((string)($audit['remote_order_status'] ?? '-')) ?></code> /
          <code><?= htmlspecialchars((string)($audit['remote_payment_status'] ?? '-')) ?></code><br>
          Internal order: <code><?= htmlspecialchars((string)($audit['old_order_status'] ?? '-')) ?></code> ->
          <code><?= htmlspecialchars((string)($audit['new_order_status'] ?? '-')) ?></code><br>
          Internal payment: <code><?= htmlspecialchars((string)($audit['old_payment_status'] ?? '-')) ?></code> ->
          <code><?= htmlspecialchars((string)($audit['new_payment_status'] ?? '-')) ?></code>
        </div>
        <?php if (!empty($audit['note'])): ?>
        <div style="margin-top:10px;background:#f8fafc;border-radius:8px;padding:10px;font-size:.82rem">
          <?= htmlspecialchars((string)$audit['note']) ?>
        </div>
        <?php endif; ?>
        <details style="margin-top:10px">
          <summary style="cursor:pointer;color:var(--coffee-brown)">Lihat payload audit</summary>
          <pre style="white-space:pre-wrap;background:#f8fafc;padding:10px;border-radius:8px;overflow:auto;font-size:.76rem;margin-top:8px"><?= htmlspecialchars($fmtJson($audit['payload_preview'] ?? null)) ?></pre>
        </details>
      </div>
      <?php endforeach; ?>
      <?php if ($audits === []): ?>
      <div style="color:var(--text-light)">Belum ada audit webhook Moka untuk cabang ini.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::renderLayout('Moka Webhook Test', $content, 'branch_admin');
