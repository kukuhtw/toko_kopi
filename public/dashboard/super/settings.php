<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf, Sanitize};
use App\Config\Database;
use App\Models\BranchModel;
use App\Plugin\HookManager;

Auth::startSession();
Auth::requireRole('super_admin');

$db        = Database::getInstance();
$user      = Auth::user();
$message   = '';
$pwMessage = '';
$pwError   = '';
$branchModel = new BranchModel();
$branches = $branchModel->getActive();
$selectedBranchId = (int)($_GET['branch_id'] ?? $_POST['branch_id'] ?? ($branches[0]['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? 'update_settings';

    if ($action === 'save_plugin_settings') {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($_POST['plugin_slug'] ?? '')));
        $selectedBranchId = (int)($_POST['branch_id'] ?? 0);

        if ($slug !== '' && $selectedBranchId > 0) {
            $skipKeys = ['action', 'plugin_slug', 'branch_id', CSRF_TOKEN_NAME];
            foreach ($_POST as $rawKey => $rawVal) {
                if (in_array($rawKey, $skipKeys, true)) { continue; }
                $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string)$rawKey));
                if ($key === '') { continue; }
                $db->prepare(
                    'INSERT INTO plugin_branch_settings (plugin_slug, branch_id, setting_key, setting_val)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)'
                )->execute([$slug, $selectedBranchId, $key, (string)$rawVal]);
            }
            $message = 'Pengaturan plugin cabang disimpan.';
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
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
        $llmProvider = $_POST['llm_provider'] ?? 'none';
        // OpenRouter supports custom model IDs typed manually
        $llmModel = Sanitize::string($_POST['llm_model'] ?? '');
        if ($llmProvider === 'openrouter' && trim($_POST['llm_model_custom'] ?? '') !== '') {
            $llmModel = Sanitize::string(trim($_POST['llm_model_custom']));
        }

        $settings = [
            'app_name'       => Sanitize::string($_POST['app_name'] ?? ''),
            'app_currency'   => $_POST['app_currency']  ?? 'IDR',
            'app_language'   => $_POST['app_language']  ?? 'id',
            'chatbot_name'   => Sanitize::string($_POST['chatbot_name'] ?? ''),
            'llm_provider'   => $llmProvider,
            'llm_api_key'    => trim($_POST['llm_api_key'] ?? ''),
            'llm_model'      => $llmModel,
        ];
        foreach ($settings as $key => $val) {
            $db->prepare('UPDATE app_settings SET setting_val = ? WHERE setting_key = ?')
               ->execute([$val, $key]);
        }
        $message = 'Pengaturan disimpan.';
    }
}

$rows = $db->query('SELECT setting_key, setting_val FROM app_settings')->fetchAll();
$cfg  = array_column($rows, 'setting_val', 'setting_key');

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="card">
  <div class="card-title">⚙️ Pengaturan Global Aplikasi</div>
  <form method="POST">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="update_settings">
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Nama Brand</label>
        <input type="text" name="app_name" class="form-control" value="<?= htmlspecialchars($cfg['app_name'] ?? 'Toko Kopi') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Nama Chatbot</label>
        <input type="text" name="chatbot_name" class="form-control" value="<?= htmlspecialchars($cfg['chatbot_name'] ?? 'Kopi Bot') ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Mata Uang Default</label>
        <select name="app_currency" class="form-control">
          <?php foreach (['IDR','USD','SGD','AUD'] as $c): ?>
          <option value="<?= $c ?>" <?= ($cfg['app_currency'] ?? 'IDR') === $c ? 'selected' : '' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Bahasa Default</label>
        <select name="app_language" class="form-control">
          <option value="id" <?= ($cfg['app_language'] ?? 'id') === 'id' ? 'selected' : '' ?>>Bahasa Indonesia</option>
          <option value="en" <?= ($cfg['app_language'] ?? 'id') === 'en' ? 'selected' : '' ?>>English</option>
        </select>
      </div>
    </div>

    <h4 style="margin:20px 0 12px;color:var(--coffee-dark)">🤖 LLM / AI Settings</h4>
    <div class="alert alert-info">LLM bersifat opsional. Tanpa LLM, chatbot menggunakan rule-based intent detection.</div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="llm_provider">LLM Provider</label>
        <select id="llm_provider" name="llm_provider" class="form-control" onchange="syncModels()">
          <option value="none"        <?= ($cfg['llm_provider'] ?? 'none') === 'none'        ? 'selected' : '' ?>>None (Rule-based)</option>
          <option value="openai"      <?= ($cfg['llm_provider'] ?? 'none') === 'openai'      ? 'selected' : '' ?>>OpenAI</option>
          <option value="gemini"      <?= ($cfg['llm_provider'] ?? 'none') === 'gemini'      ? 'selected' : '' ?>>Google Gemini</option>
          <option value="anthropic"   <?= ($cfg['llm_provider'] ?? 'none') === 'anthropic'   ? 'selected' : '' ?>>Anthropic</option>
          <option value="openrouter"  <?= ($cfg['llm_provider'] ?? 'none') === 'openrouter'  ? 'selected' : '' ?>>OpenRouter (200+ model)</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="llm_model">Model</label>
        <select id="llm_model" name="llm_model" class="form-control"></select>
        <div id="llm_model_custom_wrap" style="display:none;margin-top:6px">
          <input type="text" id="llm_model_custom" name="llm_model_custom" class="form-control"
                 value="<?= ($cfg['llm_provider'] ?? '') === 'openrouter' ? htmlspecialchars($cfg['llm_model'] ?? '') : '' ?>"
                 placeholder="Model ID kustom, cth: google/gemini-2.0-flash-001"
                 style="font-size:0.85em">
          <small style="color:var(--text-light)">Isi untuk override pilihan di atas — lihat daftar di <strong>openrouter.ai/models</strong></small>
        </div>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label" for="llm_api_key">API Key</label>
      <input id="llm_api_key" type="password" name="llm_api_key" class="form-control" value="<?= htmlspecialchars($cfg['llm_api_key'] ?? '') ?>" placeholder="sk-... / sk-ant-... / sk-or-...">
    </div>
    <script>
    const LLM_MODELS = {
      none: [],
      openai: [
        {v:'gpt-4.1',        l:'GPT-4.1 (Latest)'},
        {v:'gpt-4.1-mini',   l:'GPT-4.1 Mini'},
        {v:'gpt-4.1-nano',   l:'GPT-4.1 Nano (Fastest)'},
        {v:'gpt-4o',         l:'GPT-4o'},
        {v:'gpt-4o-mini',    l:'GPT-4o Mini (Recommended)'},
        {v:'gpt-4-turbo',    l:'GPT-4 Turbo'},
        {v:'o4-mini',        l:'o4-mini (Reasoning)'},
        {v:'o3-mini',        l:'o3-mini (Reasoning)'},
        {v:'gpt-3.5-turbo',  l:'GPT-3.5 Turbo (Legacy)'},
      ],
      gemini: [
        {v:'gemini-2.5-flash',       l:'Gemini 2.5 Flash'},
        {v:'gemini-2.5-flash-lite',  l:'Gemini 2.5 Flash Lite'},
        {v:'gemini-2.0-flash',       l:'Gemini 2.0 Flash (Recommended)'},
        {v:'gemini-1.5-flash',       l:'Gemini 1.5 Flash'},
        {v:'gemini-1.5-pro',         l:'Gemini 1.5 Pro'},
      ],
      anthropic: [
        {v:'claude-opus-4-7',              l:'Claude Opus 4.7 (Most Capable)'},
        {v:'claude-sonnet-4-6',            l:'Claude Sonnet 4.6 (Recommended)'},
        {v:'claude-haiku-4-5-20251001',    l:'Claude Haiku 4.5 (Fastest)'},
        {v:'claude-3-5-sonnet-20241022',   l:'Claude 3.5 Sonnet'},
        {v:'claude-3-5-haiku-20241022',    l:'Claude 3.5 Haiku'},
      ],
      openrouter: [
        {v:'openai/gpt-4o-mini',                 l:'GPT-4o Mini (Recommended)'},
        {v:'openai/gpt-4.1-mini',                l:'GPT-4.1 Mini'},
        {v:'openai/gpt-4.1-nano',                l:'GPT-4.1 Nano (Fastest)'},
        {v:'openai/gpt-4o',                      l:'GPT-4o'},
        {v:'openai/gpt-4.1',                     l:'GPT-4.1'},
        {v:'anthropic/claude-haiku-4-5',         l:'Claude Haiku 4.5'},
        {v:'anthropic/claude-sonnet-4-5',        l:'Claude Sonnet 4.5'},
        {v:'google/gemini-2.0-flash-001',        l:'Gemini 2.0 Flash (Murah)'},
        {v:'google/gemini-flash-1.5',            l:'Gemini 1.5 Flash'},
        {v:'meta-llama/llama-3.3-70b-instruct',  l:'Llama 3.3 70B'},
        {v:'meta-llama/llama-3.1-8b-instruct',   l:'Llama 3.1 8B (Ultra-murah)'},
        {v:'deepseek/deepseek-chat',             l:'DeepSeek V3 (Sangat murah)'},
        {v:'mistralai/mistral-7b-instruct',      l:'Mistral 7B (Ultra-murah)'},
        {v:'qwen/qwen-2.5-72b-instruct',         l:'Qwen 2.5 72B'},
      ],
    };
    const SAVED_MODEL = <?= json_encode($cfg['llm_model'] ?? 'gpt-4o-mini') ?>;
    function syncModels() {
      const provider    = document.getElementById('llm_provider').value;
      const sel         = document.getElementById('llm_model');
      const customWrap  = document.getElementById('llm_model_custom_wrap');
      const models      = LLM_MODELS[provider] || [];

      customWrap.style.display = provider === 'openrouter' ? 'block' : 'none';

      sel.innerHTML = '';
      if (!models.length) {
        sel.disabled = true;
        sel.innerHTML = '<option value="">— tidak digunakan —</option>';
        return;
      }
      sel.disabled = false;
      models.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.v;
        opt.textContent = m.l;
        if (m.v === SAVED_MODEL) opt.selected = true;
        sel.appendChild(opt);
      });
    }
    syncModels();
    </script>

    <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
  </form>
</div>

<div class="card" style="margin-top:20px">
  <div class="card-title">🏢 Pengaturan Plugin Per Cabang</div>
  <form method="GET" style="margin-bottom:16px;max-width:420px">
    <label class="form-label" for="plugin_branch_id">Pilih Cabang</label>
    <div style="display:flex;gap:10px;align-items:end">
      <select id="plugin_branch_id" name="branch_id" class="form-control">
        <?php foreach ($branches as $branch): ?>
          <option value="<?= (int)$branch['id'] ?>" <?= $selectedBranchId === (int)$branch['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars((string)$branch['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-outline">Tampilkan</button>
    </div>
    <small style="color:var(--text-light)">Pengaturan sensitif per cabang seperti payment gateway dan SMTP notifikasi dikelola dari sini.</small>
  </form>

  <?php
  $pluginSections = HookManager::applyFilters('super.settings.sections', [], $selectedBranchId);
  if (empty($pluginSections)):
  ?>
    <div style="color:var(--text-light);font-size:.9rem">Belum ada plugin yang menambahkan section pengaturan super admin.</div>
  <?php else: ?>
    <?php foreach ($pluginSections as $section): ?>
      <?= $section ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:20px">
  <div class="card-title">Ganti Password Akun Super Admin</div>
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

<?php
$content = ob_get_clean();
echo View::renderLayout('App Settings', $content, 'super_admin');
