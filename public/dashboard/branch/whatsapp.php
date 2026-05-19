<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf, Sanitize};
use App\Config\Database;
use App\Models\BranchModel;

Auth::startSession();
Auth::requireLogin();

$user     = Auth::user();
$branchId = (int)$user['branch_id'];
if (!$branchId) { exit('Access denied'); }

$db      = Database::getInstance();
$message = '';
$branchModel = new BranchModel();
$sharedInboxEnabled = $branchModel->getSetting($branchId, 'whatsapp_shared_inbox_enabled', '0') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $waNumberDel = Sanitize::phone($_POST['wa_number_del'] ?? '');
        if ($waNumberDel) {
            $db->prepare(
                'DELETE FROM branch_whatsapp_settings WHERE branch_id=? AND wa_number=?'
            )->execute([$branchId, $waNumberDel]);
            $message = 'Nomor ' . $waNumberDel . ' dihapus.';
        }
    } else {
        $providerId = (int)$_POST['provider_id'];
        $waNumber   = Sanitize::phone($_POST['wa_number'] ?? '');
        $apiKey     = trim($_POST['api_key']       ?? '');
        $apiSecret  = trim($_POST['api_secret']    ?? '');
        $token      = trim($_POST['webhook_token'] ?? '');

        if ($providerId && $waNumber) {
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
$stmt = $db->prepare(
    'SELECT bws.*, wp.name AS provider_name
     FROM branch_whatsapp_settings bws
     JOIN whatsapp_providers wp ON bws.provider_id = wp.id
     WHERE bws.branch_id = ? AND wp.adapter_class NOT IN (?, ?, ?, ?, ?)'
);
$stmt->execute([$branchId, 'FonnteProvider', 'VonageProvider', 'TwilioProvider', 'BaileysBridgeProvider', 'MessageBirdProvider']);
$settings = $stmt->fetchAll();

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:20px">
  <div class="card-title">📡 Webhook URL</div>
  <p style="font-size:.9rem;color:var(--text-mid);margin-bottom:8px">Daftarkan URL ini ke provider WhatsApp kamu:</p>
  <code id="webhookUrl" style="background:var(--coffee-cream);padding:10px 14px;border-radius:8px;display:block;font-size:.85rem;word-break:break-all">
    <?= BASE_URL ?>/api/whatsapp/webhook.php?branch=<?= $branchId ?>
  </code>
  <button type="button" onclick="copyWebhook()" style="margin-top:8px;font-size:.8rem" class="btn btn-outline">📋 Salin URL</button>
  <p style="font-size:.78rem;color:var(--text-light);margin-top:6px">
    URL ini sudah spesifik untuk cabang ini (branch <?= $branchId ?>). Setiap cabang memiliki URL webhook yang berbeda.
  </p>
  <p style="font-size:.82rem;color:var(--text-mid);margin-top:10px">
    Butuh contoh payload Fonnte atau Meta Cloud API?
    <a href="<?= BASE_URL ?>/technical-whatsapp.php" style="font-weight:600">Lihat penjelasan teknis webhook</a>.
  </p>
  <p style="font-size:.82rem;color:var(--text-mid);margin-top:6px">
    Untuk Fonnte, Twilio, Baileys, MessageBird, dan Vonage, gunakan section plugin masing-masing di halaman Pengaturan cabang.
  </p>
  <p style="font-size:.82rem;color:var(--text-mid);margin-top:6px">
    Kalau satu cabang ingin membuka WhatsApp, Telegram, dan Discord sekaligus,
    <a href="<?= BASE_URL ?>/technical-multichannel.php" style="font-weight:600">lihat arsitektur multi-channel</a>.
  </p>
  <div style="margin-top:12px;padding:12px 14px;border-radius:10px;background:<?= $sharedInboxEnabled ? '#eef8ee' : '#faf6ef' ?>;border:1px solid <?= $sharedInboxEnabled ? '#b9dfbf' : '#ead9bc' ?>;font-size:.84rem;line-height:1.65;color:var(--text-mid)">
    <strong>Mode Channel Saat Ini:</strong>
    <?= $sharedInboxEnabled
      ? ' Shared inbox aktif. Nomor WhatsApp cabang ini dipakai sebagai pintu masuk semua cabang aktif, dan customer harus memilih cabang di awal chat.'
      : ' Mode default 1 cabang = 1 channel WhatsApp. Customer yang masuk dari nomor ini langsung diarahkan ke cabang ini.' ?>
    <br><br>
    Ubah mode ini dari <a href="<?= BASE_URL ?>/dashboard/branch/settings.php" style="font-weight:600">Pengaturan Cabang</a>.
  </div>
</div>
<script>
function copyWebhook() {
  const url = document.getElementById('webhookUrl').textContent.trim();
  navigator.clipboard.writeText(url).then(() => alert('URL disalin!'));
}
</script>

<div class="card" style="margin-bottom:20px">
  <div class="card-title">⚙️ Konfigurasi WhatsApp Bot</div>
  <p style="font-size:.82rem;color:var(--text-mid);margin-bottom:12px">
    Provider legacy di halaman ini tidak lagi menampilkan Fonnte, Twilio, Baileys, MessageBird, dan Vonage karena semuanya sudah dipindah ke plugin.
  </p>
  <?php if ($sharedInboxEnabled): ?>
  <p style="font-size:.82rem;color:#8a5a12;background:#fff7e6;border:1px solid #f2d19a;border-radius:8px;padding:10px 12px;margin-bottom:12px;line-height:1.6">
    Shared inbox sedang aktif. Pastikan nomor/provider WhatsApp di cabang ini memang menjadi nomor host untuk semua cabang.
  </p>
  <?php endif; ?>
  <form method="POST">
    <?= Csrf::field() ?>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="selectProvider">Provider *</label>
        <select name="provider_id" id="selectProvider" class="form-control" required>
          <option value="">Pilih provider...</option>
          <?php foreach ($providers as $p): ?>
          <option value="<?= $p['id'] ?>" <?= !empty($settings[0]) && $settings[0]['provider_id']==$p['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="inputWaNumber">Nomor WhatsApp Bot *</label>
        <input type="text" name="wa_number" id="inputWaNumber" class="form-control" placeholder="6281234567890"
               value="<?= htmlspecialchars($settings[0]['wa_number'] ?? '') ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label" id="lblApiKey" for="inputApiKey">API Key</label>
        <input type="text" name="api_key" id="inputApiKey" class="form-control"
               value="<?= htmlspecialchars($settings[0]['api_key'] ?? '') ?>">
        <small id="hintApiKey" style="color:var(--text-mid);font-size:.8rem"></small>
      </div>
      <div class="form-group" id="wrapApiSecret">
        <label class="form-label" for="inputApiSecret">API Secret / App Secret</label>
        <input type="password" name="api_secret" id="inputApiSecret" class="form-control">
        <small id="hintApiSecret" style="color:var(--text-mid);font-size:.8rem">Untuk Meta atau provider legacy lain yang butuh secret</small>
      </div>
    </div>
    <div class="form-group" id="wrapWebhookToken">
      <label class="form-label" for="inputWebhookToken">Webhook Verify Token</label>
      <input type="text" name="webhook_token" id="inputWebhookToken" class="form-control"
             value="<?= htmlspecialchars($settings[0]['webhook_token'] ?? '') ?>"
             placeholder="Token untuk verifikasi webhook Meta">
      <small style="color:var(--text-mid);font-size:.8rem">Hanya untuk Meta Cloud API</small>
    </div>
    <button type="submit" class="btn btn-primary">Simpan</button>
  </form>

<script>
const providerConfigs = {
  'Fonnte': {
    apiKeyLabel: 'Token Fonnte *',
    apiKeyHint:  'Salin dari dashboard Fonnte → Device → Token',
    showSecret:  false,
    showVerify:  false,
  },
  'Wablas': {
    apiKeyLabel: 'Token Wablas *',
    apiKeyHint:  'Salin dari dashboard Wablas → Devices → Token',
    showSecret:  false,
    showVerify:  false,
  },
  'Meta Cloud API': {
    apiKeyLabel: 'Access Token (Meta) *',
    apiKeyHint:  'Permanent access token dari Meta for Developers',
    showSecret:  true,
    showVerify:  true,
    secretHint:  'App Secret dari Meta for Developers → App → Settings → Basic (untuk verifikasi X-Hub-Signature-256)',
  },
  'Vonage': {
    apiKeyLabel: 'API Key (Vonage) *',
    apiKeyHint:  'Salin dari dashboard Vonage → API Settings → API key',
    showSecret:  true,
    showVerify:  false,
    secretHint:  'API Secret dari dashboard Vonage → API Settings → API secret',
  },
};

function updateProviderFields() {
  const select = document.getElementById('selectProvider');
  const name   = select.options[select.selectedIndex]?.text?.trim() ?? '';
  const cfg    = providerConfigs[name] ?? { apiKeyLabel: 'API Key', apiKeyHint: '', showSecret: true, showVerify: true };

  document.getElementById('lblApiKey').textContent          = cfg.apiKeyLabel;
  document.getElementById('hintApiKey').textContent         = cfg.apiKeyHint;
  document.getElementById('wrapApiSecret').style.display    = cfg.showSecret ? '' : 'none';
  document.getElementById('wrapWebhookToken').style.display = cfg.showVerify ? '' : 'none';
  if (cfg.secretHint !== undefined) {
    document.getElementById('hintApiSecret').textContent = cfg.secretHint;
  }
}

document.getElementById('selectProvider').addEventListener('change', updateProviderFields);
updateProviderFields(); // run on page load
</script>
</div>

<?php if (!empty($settings)): ?>
<div class="card">
  <div class="card-title">✅ Konfigurasi Aktif</div>
  <?php foreach ($settings as $s): ?>
  <div style="padding:10px 0;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span class="badge badge-blue"><?= htmlspecialchars($s['provider_name']) ?></span>
    <strong><?= htmlspecialchars($s['wa_number']) ?></strong>
    <span class="badge <?= $s['is_active'] ? 'badge-green' : 'badge-gray' ?>">
      <?= $s['is_active'] ? 'Aktif' : 'Nonaktif' ?>
    </span>
    <form method="POST" style="margin:0;margin-left:auto"
          onsubmit="return confirm('Hapus nomor <?= htmlspecialchars($s['wa_number']) ?>?')">
      <?= Csrf::field() ?>
      <input type="hidden" name="action"        value="delete">
      <input type="hidden" name="wa_number_del" value="<?= htmlspecialchars($s['wa_number']) ?>">
      <button type="submit" class="btn btn-xs btn-outline" style="color:var(--danger)">Hapus</button>
    </form>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
echo View::renderLayout('WhatsApp Bot Settings', $content, 'branch_admin');
