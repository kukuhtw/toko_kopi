<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf, Sanitize};
use App\Config\Database;

Auth::startSession();
Auth::requireRole('super_admin');

$db       = Database::getInstance();
$message  = '';
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_wa') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare(
                'UPDATE branch_whatsapp_settings SET is_active = IF(is_active=1, 0, 1) WHERE id=?'
            )->execute([$id]);
            $message = 'Status diubah.';
        }
    } elseif ($action === 'delete_wa') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM branch_whatsapp_settings WHERE id=?')->execute([$id]);
            $message = 'Pengaturan dihapus.';
        }
    } elseif ($action === 'save_branch_wa') {
        $branchId   = (int)$_POST['branch_id'];
        $providerId = (int)$_POST['provider_id'];
        $waNumber   = Sanitize::phone($_POST['wa_number'] ?? '');
        $apiKey     = trim($_POST['api_key']     ?? '');
        $apiSecret  = trim($_POST['api_secret']  ?? '');
        $token      = trim($_POST['webhook_token'] ?? '');

        if (!$branchId || !$providerId || !$waNumber) {
            $error = 'Branch, provider, dan nomor WA wajib diisi.';
        } else {
            $db->prepare(
                'INSERT INTO branch_whatsapp_settings (branch_id, provider_id, wa_number, api_key, api_secret, webhook_token)
                 VALUES (?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE provider_id=VALUES(provider_id), api_key=VALUES(api_key),
                 api_secret=VALUES(api_secret), webhook_token=VALUES(webhook_token)'
            )->execute([$branchId, $providerId, $waNumber, $apiKey, $apiSecret, $token]);
            $message = 'Pengaturan WhatsApp disimpan.';
        }
    }
}

$providers = $db->query(
    "SELECT * FROM whatsapp_providers
     WHERE is_active=1 AND adapter_class NOT IN ('FonnteProvider', 'VonageProvider', 'TwilioProvider', 'BaileysBridgeProvider', 'MessageBirdProvider')
     ORDER BY name"
)->fetchAll();
$branches  = $db->query('SELECT * FROM branches WHERE is_active=1 ORDER BY name')->fetchAll();
$settings  = $db->query(
    "SELECT bws.*, b.name AS branch_name, wp.name AS provider_name,
            COALESCE(bs.setting_val, '0') AS whatsapp_shared_inbox_enabled
     FROM branch_whatsapp_settings bws
     JOIN branches b ON bws.branch_id = b.id
     JOIN whatsapp_providers wp ON bws.provider_id = wp.id
     LEFT JOIN branch_settings bs
       ON bs.branch_id = b.id AND bs.setting_key = 'whatsapp_shared_inbox_enabled'
     WHERE wp.adapter_class NOT IN ('FonnteProvider', 'VonageProvider', 'TwilioProvider', 'BaileysBridgeProvider', 'MessageBirdProvider')
     ORDER BY b.name"
)->fetchAll();

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="section-header">
  <h2>Pengaturan WhatsApp</h2>
  <button class="btn btn-primary" onclick="document.getElementById('addWaModal').classList.remove('hidden')">+ Tambah Pengaturan</button>
</div>

<!-- Webhook URL Info -->
<div class="card" style="margin-bottom:20px">
  <div class="card-title">📡 Webhook URL</div>
  <p style="color:var(--text-mid);font-size:.9rem;margin-bottom:12px">Arahkan webhook dari provider WA ke URL berikut:</p>
  <code style="background:var(--coffee-cream);padding:10px 14px;border-radius:8px;display:block;font-size:.85rem;word-break:break-all">
    <?= BASE_URL ?>/api/whatsapp/webhook.php
  </code>
  <p style="font-size:.82rem;color:var(--text-mid);margin-top:10px">
    Penjelasan teknis payload, verifikasi webhook, dan alur provider tersedia di
    <a href="<?= BASE_URL ?>/technical-whatsapp.php" style="font-weight:600">halaman dokumentasi webhook</a>.
  </p>
  <p style="font-size:.82rem;color:var(--text-mid);margin-top:6px">
    Untuk desain satu cabang dengan beberapa channel aktif, buka juga
    <a href="<?= BASE_URL ?>/technical-multichannel.php" style="font-weight:600">dokumentasi multi-channel</a>.
  </p>
  <p style="font-size:.82rem;color:var(--text-mid);margin-top:6px">
    Fonnte, Twilio, Baileys, MessageBird, dan Vonage sekarang dikelola lewat plugin per cabang di halaman Pengaturan cabang, bukan dari daftar provider legacy ini.
  </p>
  <p style="font-size:.82rem;color:var(--text-mid);margin-top:6px">
    Cabang yang mengaktifkan WhatsApp Shared Inbox akan memakai nomor host itu untuk menerima chat semua cabang aktif, lalu customer diminta memilih cabang di awal.
  </p>
</div>

<div class="card">
  <div class="card-title">Daftar Pengaturan per Cabang</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Cabang</th><th>Provider</th><th>Nomor WA</th><th>Mode</th><th>Status</th><th>Webhook Token</th><th>Aksi</th></tr>
      </thead>
      <tbody>
      <?php foreach ($settings as $s): ?>
      <tr>
        <td><strong><?= htmlspecialchars($s['branch_name']) ?></strong></td>
        <td><span class="badge badge-blue"><?= htmlspecialchars($s['provider_name']) ?></span></td>
        <td><?= htmlspecialchars($s['wa_number']) ?></td>
        <td><span class="badge <?= ($s['whatsapp_shared_inbox_enabled'] ?? '0') === '1' ? 'badge-blue' : 'badge-gray' ?>"><?= ($s['whatsapp_shared_inbox_enabled'] ?? '0') === '1' ? 'Shared Inbox' : 'Per Cabang' ?></span></td>
        <td><span class="badge <?= $s['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= $s['is_active'] ? 'Aktif' : 'Non-aktif' ?></span></td>
        <td style="font-size:.8rem;color:var(--text-light)"><?= htmlspecialchars(substr($s['webhook_token'] ?? '—', 0, 20)) ?>…</td>
        <td style="display:flex;gap:6px;flex-wrap:wrap">
          <form method="POST" style="display:inline">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="toggle_wa">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button class="btn btn-xs btn-outline" title="<?= $s['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
              <?= $s['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
            </button>
          </form>
          <form method="POST" style="display:inline"
                onsubmit="return confirm('Hapus pengaturan WA cabang <?= htmlspecialchars($s['branch_name']) ?>?')">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="delete_wa">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button class="btn btn-xs btn-outline" style="color:var(--danger)">Hapus</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($settings)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--text-light)">Belum ada pengaturan</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add WhatsApp Modal -->
<div id="addWaModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Tambah Pengaturan WhatsApp</div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="save_branch_wa">
      <div class="form-group">
        <label class="form-label">Cabang *</label>
        <select name="branch_id" class="form-control" required>
          <option value="">Pilih cabang...</option>
          <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Provider *</label>
        <select name="provider_id" class="form-control" required>
          <option value="">Pilih provider...</option>
          <?php foreach ($providers as $p): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nomor WhatsApp *</label>
          <input type="text" name="wa_number" class="form-control" placeholder="6281234567890" required>
        </div>
        <div class="form-group">
          <label class="form-label">Webhook Token</label>
          <input type="text" name="webhook_token" class="form-control" placeholder="Untuk verifikasi Meta">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">API Key</label>
          <input type="text" name="api_key" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">API Secret</label>
          <input type="password" name="api_secret" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addWaModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::renderLayout('WhatsApp Provider Settings', $content, 'super_admin');
