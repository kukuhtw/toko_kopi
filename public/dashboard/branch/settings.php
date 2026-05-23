<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf, Sanitize};
use App\Models\BranchModel;
use App\Config\Database;
use App\Plugin\HookManager;

Auth::startSession();
Auth::requireLogin();

$user     = Auth::user();
$branchId = (int)$user['branch_id'];
if (!$branchId) { exit('Access denied'); }

$branchModel = new BranchModel();
$branch      = $branchModel->find($branchId);
$settings    = $branchModel->getAllSettings($branchId);
$db          = Database::getInstance();
$message     = '';
$pwMessage   = '';
$pwError     = '';

// Dual-language setting keys
$dualLangKeys = ['name_en', 'description_id', 'description_en', 'greeting_id', 'greeting_en', 'hours_id', 'hours_en'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? 'update_settings';

    if ($action === 'save_plugin_settings') {
        // Simpan pengaturan plugin ke plugin_branch_settings
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($_POST['plugin_slug'] ?? '')));
        if ($slug !== '') {
            $skipKeys = ['action', 'plugin_slug', CSRF_TOKEN_NAME];
            foreach ($_POST as $rawKey => $rawVal) {
                if (in_array($rawKey, $skipKeys, true)) { continue; }
                $key = preg_replace('/[^a-z0-9_]/', '', strtolower($rawKey));
                if ($key === '') { continue; }
                $db->prepare(
                    'INSERT INTO plugin_branch_settings (plugin_slug, branch_id, setting_key, setting_val)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)'
                )->execute([$slug, $branchId, $key, (string)$rawVal]);
            }
            $message = 'Pengaturan plugin disimpan.';
            HookManager::doAction('settings.saved', $branchId, $_POST);
        }

    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $row = $db->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $row->execute([$user['id']]);
        $dbUser = $row->fetch();

        if (!$dbUser || !password_verify($current, $dbUser['password'])) {
            $pwError = 'Password lama tidak sesuai.';
        } elseif (strlen($new) < 6) {
            $pwError = 'Password baru minimal 6 karakter.';
        } elseif ($new !== $confirm) {
            $pwError = 'Konfirmasi password tidak cocok.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $user['id']]);
            $pwMessage = 'Password berhasil diubah.';
        }

    } else {
        // Update branch info
        $branchModel->update($branchId, [
            'name'    => Sanitize::string($_POST['name']    ?? $branch['name']),
            'address' => Sanitize::string($_POST['address'] ?? ''),
            'city'    => Sanitize::string($_POST['city']    ?? ''),
            'postal_code' => preg_replace('/\D/', '', (string)($_POST['postal_code'] ?? '')),
            'phone'   => Sanitize::string($_POST['phone']   ?? ''),
            'email'   => Sanitize::string($_POST['email']   ?? ''),
        ]);

        // Standard settings
        foreach (['currency', 'language', 'wa_number'] as $k) {
            if (isset($_POST[$k])) {
                $branchModel->setSetting($branchId, $k, Sanitize::string($_POST[$k]));
            }
        }
        $branchModel->setSetting(
            $branchId,
            'whatsapp_shared_inbox_enabled',
            isset($_POST['whatsapp_shared_inbox_enabled']) ? '1' : '0'
        );
        if (isset($_POST['timezone']) && in_array($_POST['timezone'], \DateTimeZone::listIdentifiers())) {
            $branchModel->setSetting($branchId, 'timezone', $_POST['timezone']);
        }
        if (isset($_POST['ppn_rate'])) {
            $branchModel->setSetting($branchId, 'ppn_rate', (string)max(0.0, min(100.0, (float)$_POST['ppn_rate'])));
        }

        // Dual-language settings
        foreach ($dualLangKeys as $k) {
            if (isset($_POST[$k])) {
                $branchModel->setSetting($branchId, $k, Sanitize::string($_POST[$k]));
            }
        }

        $message  = 'Pengaturan cabang disimpan.';
        $branch   = $branchModel->find($branchId);
        $settings = $branchModel->getAllSettings($branchId);
        HookManager::doAction('settings.saved', $branchId, $settings);
    }
}

$s = fn(string $key, string $default = '') => htmlspecialchars($settings[$key] ?? $default);

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<form method="POST">
<?= Csrf::field() ?>
<input type="hidden" name="action" value="update_settings">

<!-- ── Informasi Cabang ─────────────────────────────────── -->
<div class="card" style="margin-bottom:16px">
  <div class="card-title">🏪 Informasi Cabang</div>

  <!-- Nama cabang dual language -->
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="set_name">
        <span class="lang-flag">🇮🇩</span> Nama Cabang <small style="color:var(--text-light)">(Indonesia)</small>
      </label>
      <input type="text" id="set_name" name="name" class="form-control"
             value="<?= htmlspecialchars($branch['name']) ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label" for="set_name_en">
        <span class="lang-flag">🇬🇧</span> Branch Name <small style="color:var(--text-light)">(English)</small>
      </label>
      <input type="text" id="set_name_en" name="name_en" class="form-control"
             value="<?= $s('name_en') ?>" placeholder="e.g. Main Branch">
    </div>
  </div>

  <!-- Deskripsi dual language -->
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="desc_id">
        <span class="lang-flag">🇮🇩</span> Deskripsi Cabang
      </label>
      <textarea id="desc_id" name="description_id" class="form-control" rows="2"
                placeholder="Deskripsi singkat cabang untuk chatbot..."><?= $s('description_id') ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label" for="desc_en">
        <span class="lang-flag">🇬🇧</span> Branch Description
      </label>
      <textarea id="desc_en" name="description_en" class="form-control" rows="2"
                placeholder="Short branch description for chatbot..."><?= $s('description_en') ?></textarea>
    </div>
  </div>

  <!-- Info kontak -->
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="set_city">Kota / City</label>
      <input type="text" id="set_city" name="city" class="form-control"
             value="<?= htmlspecialchars($branch['city'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label" for="set_phone">Telepon / Phone</label>
      <input type="text" id="set_phone" name="phone" class="form-control"
             value="<?= htmlspecialchars($branch['phone'] ?? '') ?>">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="set_address">Alamat / Address</label>
      <textarea id="set_address" name="address" class="form-control" rows="2"><?= htmlspecialchars($branch['address'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label" for="set_postal_code">Kode Pos Cabang</label>
      <input type="text" id="set_postal_code" name="postal_code" class="form-control"
             value="<?= htmlspecialchars($branch['postal_code'] ?? '') ?>" placeholder="Contoh: 40123">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="set_email">Email</label>
      <input type="email" id="set_email" name="email" class="form-control"
             value="<?= htmlspecialchars($branch['email'] ?? '') ?>">
    </div>
  </div>
</div>

<!-- ── Konten Chatbot (Dual Language) ──────────────────── -->
<div class="card" style="margin-bottom:16px">
  <div class="card-title">🤖 Konten Chatbot
    <small style="font-size:.8rem;color:var(--text-light);font-weight:400;margin-left:8px">
      Teks yang digunakan bot saat menyambut pelanggan
    </small>
  </div>

  <!-- Greeting -->
  <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:14px;margin-bottom:14px">
    <div style="font-weight:600;font-size:.85rem;margin-bottom:10px;color:var(--text-mid)">Pesan Sambutan / Greeting</div>
    <div class="form-row">
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label" for="greeting_id">
          <span class="lang-flag">🇮🇩</span> Indonesia
        </label>
        <textarea id="greeting_id" name="greeting_id" class="form-control" rows="3"
                  placeholder="Halo! Selamat datang di {nama_cabang}. Ada yang bisa kami bantu?"><?= $s('greeting_id') ?></textarea>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label" for="greeting_en">
          <span class="lang-flag">🇬🇧</span> English
        </label>
        <textarea id="greeting_en" name="greeting_en" class="form-control" rows="3"
                  placeholder="Hello! Welcome to {branch_name}. How can we help you?"><?= $s('greeting_en') ?></textarea>
      </div>
    </div>
  </div>

  <!-- Jam Operasional -->
  <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:14px">
    <div style="font-weight:600;font-size:.85rem;margin-bottom:10px;color:var(--text-mid)">Jam Operasional / Operating Hours</div>
    <div class="form-row">
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label" for="hours_id">
          <span class="lang-flag">🇮🇩</span> Indonesia
        </label>
        <textarea id="hours_id" name="hours_id" class="form-control" rows="3"
                  placeholder="Senin–Jumat: 08.00–22.00&#10;Sabtu–Minggu: 09.00–23.00"><?= $s('hours_id') ?></textarea>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label" for="hours_en">
          <span class="lang-flag">🇬🇧</span> English
        </label>
        <textarea id="hours_en" name="hours_en" class="form-control" rows="3"
                  placeholder="Mon–Fri: 08:00–22:00&#10;Sat–Sun: 09:00–23:00"><?= $s('hours_en') ?></textarea>
      </div>
    </div>
  </div>
</div>

<!-- ── Pengaturan Umum ──────────────────────────────────── -->
<div class="card" style="margin-bottom:16px">
  <div class="card-title">⚙️ Pengaturan Umum</div>
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="set_currency">Mata Uang / Currency</label>
      <select id="set_currency" name="currency" class="form-control">
        <?php foreach (['IDR'=>'IDR — Rupiah','USD'=>'USD — US Dollar','SGD'=>'SGD — Singapore Dollar','AUD'=>'AUD — Australian Dollar','MYR'=>'MYR — Ringgit'] as $c => $label): ?>
        <option value="<?= $c ?>" <?= ($settings['currency'] ?? 'IDR') === $c ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label" for="set_language">Bahasa Utama Chatbot</label>
      <select id="set_language" name="language" class="form-control">
        <option value="id" <?= ($settings['language'] ?? 'id') === 'id' ? 'selected' : '' ?>>🇮🇩 Bahasa Indonesia</option>
        <option value="en" <?= ($settings['language'] ?? 'id') === 'en' ? 'selected' : '' ?>>🇬🇧 English</option>
        <option value="both" <?= ($settings['language'] ?? 'id') === 'both' ? 'selected' : '' ?>>🌐 Bilingual (ID + EN)</option>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="set_tz">Zona Waktu / Timezone</label>
      <select id="set_tz" name="timezone" class="form-control">
        <?php
        $tzGroups = [
          'Indonesia' => ['Asia/Jakarta'=>'WIB — Jakarta (UTC+7)','Asia/Makassar'=>'WITA — Makassar (UTC+8)','Asia/Jayapura'=>'WIT — Papua (UTC+9)'],
          'Asia'      => ['Asia/Singapore'=>'SGT — Singapore (UTC+8)','Asia/Kuala_Lumpur'=>'MYT — Kuala Lumpur (UTC+8)','Asia/Bangkok'=>'ICT — Bangkok (UTC+7)','Asia/Tokyo'=>'JST — Tokyo (UTC+9)','Asia/Dubai'=>'GST — Dubai (UTC+4)'],
          'Oceania'   => ['Australia/Sydney'=>'AEST — Sydney (UTC+10)','Australia/Melbourne'=>'AEST — Melbourne (UTC+10)'],
          'Lainnya'   => ['UTC'=>'UTC','Europe/London'=>'GMT — London','America/New_York'=>'ET — New York'],
        ];
        $curTz = $settings['timezone'] ?? 'Asia/Jakarta';
        foreach ($tzGroups as $group => $zones) { ?>
        <optgroup label="<?= $group ?>">
          <?php foreach ($zones as $tz => $label) { ?>
          <option value="<?= $tz ?>" <?= $curTz === $tz ? 'selected' : '' ?>><?= $label ?></option>
          <?php } ?>
        </optgroup>
        <?php } ?>
      </select>
    </div>
    <div class="form-group" style="max-width:180px">
      <label class="form-label" for="set_ppn">PPN / Tax (%)</label>
      <input type="number" id="set_ppn" name="ppn_rate" class="form-control"
             min="0" max="100" step="0.01"
             value="<?= $s('ppn_rate', '11') ?>"
             placeholder="0 = tidak ada PPN">
    </div>
  </div>
  <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:14px;margin:8px 0 14px">
    <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer">
      <input type="checkbox" name="whatsapp_shared_inbox_enabled" value="1"
             <?= ($settings['whatsapp_shared_inbox_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
             style="margin-top:3px">
      <span>
        <strong>Aktifkan WhatsApp Shared Inbox</strong><br>
        <small style="color:var(--text-mid);line-height:1.6">
          Jika aktif, channel WhatsApp cabang ini bisa menjadi pintu masuk untuk semua cabang aktif.
          Customer akan diminta memilih cabang terlebih dahulu di awal chat.
        </small>
      </span>
    </label>
  </div>
  <button type="submit" class="btn btn-primary">💾 Simpan Semua Pengaturan</button>
</div>

</form>

<!-- ── Plugin Settings Sections ─────────────────────────── -->
<?php
$pluginSections = HookManager::applyFilters('settings.sections', [], $branchId);
foreach ($pluginSections as $section) {
    echo $section;
}
?>

<!-- ── Ganti Password ───────────────────────────────────── -->
<div class="card">
  <div class="card-title">🔑 Ganti Password Akun</div>
  <?php if ($pwMessage): ?><div class="alert alert-success"><?= htmlspecialchars($pwMessage) ?></div><?php endif; ?>
  <?php if ($pwError):   ?><div class="alert alert-error"><?= htmlspecialchars($pwError) ?></div><?php endif; ?>
  <form method="POST" style="max-width:420px">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="change_password">
    <div class="form-group">
      <label class="form-label" for="current_password">Password Lama *</label>
      <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
    </div>
    <div class="form-group">
      <label class="form-label" for="new_password">Password Baru * <small style="color:var(--text-light)">(min 6 karakter)</small></label>
      <input type="password" id="new_password" name="new_password" class="form-control" minlength="6" required autocomplete="new-password">
    </div>
    <div class="form-group">
      <label class="form-label" for="confirm_password">Konfirmasi Password Baru *</label>
      <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="6" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary">Ganti Password</button>
  </form>
</div>

<style>
.lang-flag { font-size:1rem;margin-right:4px }
</style>

<?php
$content = ob_get_clean();
echo View::renderLayout('Pengaturan Cabang', $content, 'branch_admin');
