<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf};
use App\Plugin\PluginLoader;

Auth::startSession();
Auth::requireRole('super_admin');

/** @var ThemesPlugin|null $plugin */
$plugin = PluginLoader::get('themes');
if (!$plugin) {
    echo View::renderLayout('Tema & Branding', '<div class="card"><p style="color:var(--accent-red)">Plugin "themes" tidak aktif. Aktifkan di halaman Plugins.</p></div>', 'super_admin');
    exit;
}

$message = '';

// ── POST: simpan pengaturan ──────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();

    // Preset
    $validPresets = array_keys(ThemesPlugin::PRESETS);
    $validPresets[] = 'custom';
    $preset = $_POST['preset'] ?? 'coffee';
    if (!in_array($preset, $validPresets, true)) { $preset = 'coffee'; }
    $plugin->saveSetting('preset', $preset);

    // Branding
    $plugin->saveSetting('app_name',    mb_substr(strip_tags((string)($_POST['app_name']    ?? '')), 0, 80));
    $plugin->saveSetting('tagline',     mb_substr(strip_tags((string)($_POST['tagline']     ?? '')), 0, 120));
    $plugin->saveSetting('brand_emoji', mb_substr(strip_tags((string)($_POST['brand_emoji'] ?? '')), 0, 8));

    // Custom colors
    foreach (ThemesPlugin::getCustomVarMap() as $key => $cssVar) {
        $hex = strtolower(trim((string)($_POST['custom_' . $key] ?? '')));
        if (preg_match('/^#[0-9a-f]{6}$/', $hex)) {
            $plugin->saveSetting('custom_' . $key, $hex);
        }
    }

    $message = 'Pengaturan tema berhasil disimpan.';
}

// ── Current values ────────────────────────────────────────────

$currentPreset = $plugin->getSetting('preset', 'coffee');
$appName       = $plugin->getSetting('app_name', '');
$tagline       = $plugin->getSetting('tagline', '');
$brandEmoji    = $plugin->getSetting('brand_emoji', '');

// Custom hex values — default ke warna preset coffee
$coffeeDefault = ThemesPlugin::PRESETS['coffee']['vars'];
$customColors  = [];
foreach (ThemesPlugin::getCustomVarMap() as $key => $cssVar) {
    $saved = $plugin->getSetting('custom_' . $key, '');
    $customColors[$key] = $saved !== '' ? $saved : ($coffeeDefault[$cssVar] ?? '#cccccc');
}

// ── Render ────────────────────────────────────────────────────

ob_start();
?>
<style>
/* ── Theme Card Grid ── */
.theme-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(150px,1fr));
  gap:12px; margin-bottom:0;
}
.theme-card {
  border:2px solid var(--border);
  border-radius:var(--radius-lg);
  padding:16px 14px;
  cursor:pointer; transition:border-color .15s, box-shadow .15s;
  background:#fff; user-select:none;
}
.theme-card:hover    { border-color:var(--coffee-brown); }
.theme-card.selected { border-color:var(--coffee-brown); box-shadow:0 0 0 3px rgba(0,0,0,.06); }
.theme-card input[type=radio] { display:none; }
.theme-swatches { display:flex; gap:5px; margin-bottom:10px; }
.theme-swatches span {
  width:22px; height:22px; border-radius:50%;
  border:1px solid rgba(0,0,0,.08); flex-shrink:0;
}
.theme-name  { font-size:.82rem; font-weight:700; color:var(--text-dark); margin-bottom:2px; }
.theme-emoji { font-size:1.1rem; }
.theme-check { float:right; color:var(--coffee-brown); font-size:.9rem; }

/* ── Custom color grid ── */
.color-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(160px,1fr));
  gap:14px;
}
.color-field label { display:block; font-size:.8rem; font-weight:600; color:var(--text-mid); margin-bottom:6px; }
.color-input-wrap  { display:flex; align-items:center; gap:8px; }
.color-input-wrap input[type=color] {
  width:36px; height:36px; border:1px solid var(--border);
  border-radius:6px; cursor:pointer; padding:2px; background:#fff;
}
.color-input-wrap input[type=text] {
  font-size:.83rem; font-family:monospace;
  width:80px; padding:6px 8px;
  border:1px solid var(--border); border-radius:6px;
}

/* ── Preview Banner ── */
.preview-banner {
  border-radius:var(--radius);
  padding:14px 18px;
  display:flex; align-items:center; gap:12px;
  font-size:.88rem; font-weight:600;
  color:#fff; margin-bottom:0;
  transition:background .3s;
}
</style>

<?php if ($message): ?>
<div class="alert alert-success" style="margin-bottom:20px"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST" id="themes-form">
  <?= Csrf::field() ?>

  <!-- ── Pilih Tema ── -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-title">🎨 Pilih Tema Warna</div>
    <p style="font-size:.875rem;color:var(--text-light);margin-bottom:18px">
      Tema mengubah seluruh palet warna dashboard — sidebar, tombol, tautan, dan latar belakang.
    </p>

    <div class="theme-grid" id="theme-grid">
      <?php foreach (ThemesPlugin::PRESETS as $key => $preset):
        $isSelected = ($currentPreset === $key);
        $swatches   = $preset['swatches'];
      ?>
      <label class="theme-card <?= $isSelected ? 'selected' : '' ?>" data-preset="<?= $key ?>">
        <input type="radio" name="preset" value="<?= $key ?>" <?= $isSelected ? 'checked' : '' ?>>
        <div class="theme-swatches">
          <?php foreach ($swatches as $hex): ?>
          <span style="background:<?= htmlspecialchars($hex) ?>"></span>
          <?php endforeach; ?>
        </div>
        <div class="theme-emoji"><?= $preset['emoji'] ?></div>
        <div class="theme-name">
          <?= htmlspecialchars($preset['label']) ?>
          <?= $isSelected ? '<span class="theme-check">✓</span>' : '' ?>
        </div>
      </label>
      <?php endforeach; ?>

      <!-- Custom -->
      <?php $isCustom = ($currentPreset === 'custom'); ?>
      <label class="theme-card <?= $isCustom ? 'selected' : '' ?>" data-preset="custom">
        <input type="radio" name="preset" value="custom" <?= $isCustom ? 'checked' : '' ?>>
        <div class="theme-swatches">
          <?php foreach (['dark','brown','light','cream'] as $k): ?>
          <span id="swatch-<?= $k ?>" style="background:<?= htmlspecialchars($customColors[$k]) ?>"></span>
          <?php endforeach; ?>
        </div>
        <div class="theme-emoji">🎨</div>
        <div class="theme-name">
          Custom
          <?= $isCustom ? '<span class="theme-check">✓</span>' : '' ?>
        </div>
      </label>
    </div>

    <!-- Preview bar -->
    <div id="preview-banner" class="preview-banner"
         style="margin-top:18px;background:<?= htmlspecialchars(ThemesPlugin::PRESETS[$currentPreset === 'custom' ? 'coffee' : ($currentPreset ?: 'coffee')]['vars']['--coffee-dark'] ?? '#2C1A0E') ?>">
      <span id="preview-emoji" style="font-size:1.3rem"><?= htmlspecialchars(ThemesPlugin::PRESETS[$currentPreset === 'custom' ? 'coffee' : ($currentPreset ?: 'coffee')]['emoji'] ?? '☕') ?></span>
      <span>Preview sidebar &mdash; tema <strong id="preview-name"><?= htmlspecialchars(ThemesPlugin::PRESETS[$currentPreset === 'custom' ? 'coffee' : ($currentPreset ?: 'coffee')]['label'] ?? 'Coffee') ?></strong></span>
      <span style="margin-left:auto;opacity:.7;font-size:.78rem;font-weight:400">
        warna aktif setelah simpan &amp; refresh halaman
      </span>
    </div>
  </div>

  <!-- ── Warna Custom ── -->
  <div class="card" id="custom-section" style="margin-bottom:20px;<?= $isCustom ? '' : 'display:none' ?>">
    <div class="card-title">🖌️ Warna Custom</div>
    <p style="font-size:.875rem;color:var(--text-light);margin-bottom:18px">
      Kustomisasi warna palette secara bebas. Gunakan format hex <code>#rrggbb</code>.
    </p>
    <div class="color-grid">
      <?php
      $colorLabels = [
          'dark'   => 'Sidebar (Gelap)',
          'brown'  => 'Primary (Tombol & Link)',
          'medium' => 'Medium Accent',
          'light'  => 'Light Accent',
          'cream'  => 'Background Halaman',
          'white'  => 'Background Kartu',
          'border' => 'Warna Border',
      ];
      foreach ($colorLabels as $key => $label): ?>
      <div class="color-field">
        <label for="cc_<?= $key ?>"><?= htmlspecialchars($label) ?></label>
        <div class="color-input-wrap">
          <input type="color" id="cc_<?= $key ?>_picker"
                 value="<?= htmlspecialchars($customColors[$key]) ?>"
                 oninput="syncColor('<?= $key ?>', this.value)">
          <input type="text" id="cc_<?= $key ?>" name="custom_<?= $key ?>"
                 value="<?= htmlspecialchars($customColors[$key]) ?>"
                 maxlength="7"
                 oninput="syncColorFromText('<?= $key ?>', this.value)">
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Nama & Branding ── -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-title">✏️ Nama & Identitas Bisnis</div>
    <p style="font-size:.875rem;color:var(--text-light);margin-bottom:18px">
      Ditampilkan di logo sidebar dashboard. Kosongkan untuk menggunakan nilai default.
    </p>

    <div class="form-row" style="align-items:flex-start">
      <div class="form-group" style="max-width:80px">
        <label class="form-label" for="brand_emoji">Emoji / Icon</label>
        <input type="text" id="brand_emoji" name="brand_emoji" class="form-control"
               value="<?= htmlspecialchars($brandEmoji) ?>" placeholder="☕"
               style="font-size:1.3rem;text-align:center">
        <small style="color:var(--text-light)">Satu emoji</small>
      </div>
      <div class="form-group" style="flex:1;max-width:360px">
        <label class="form-label" for="app_name">Nama Aplikasi</label>
        <input type="text" id="app_name" name="app_name" class="form-control"
               value="<?= htmlspecialchars($appName) ?>" placeholder="Toko Kopi"
               maxlength="80">
        <small style="color:var(--text-light)">Muncul di logo sidebar dan judul halaman browser.</small>
      </div>
    </div>

    <div class="form-group" style="max-width:460px">
      <label class="form-label" for="tagline">Tagline Bisnis</label>
      <input type="text" id="tagline" name="tagline" class="form-control"
             value="<?= htmlspecialchars($tagline) ?>" placeholder="Premium Coffee Experience"
             maxlength="120">
      <small style="color:var(--text-light)">Ditampilkan sebagai subtitle kecil di bawah nama di sidebar.</small>
    </div>

    <!-- Live preview logo -->
    <div style="margin-top:16px">
      <div style="font-size:.75rem;font-weight:600;color:var(--text-light);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Preview Sidebar Logo</div>
      <div id="logo-preview" style="
        background:var(--coffee-dark);color:#fff;
        display:inline-block;padding:16px 20px;border-radius:10px;
        font-size:1.1rem;font-weight:700;min-width:220px;
      ">
        <span id="lp-emoji"><?= $brandEmoji !== '' ? htmlspecialchars($brandEmoji) : '☕' ?></span>
        <span id="lp-name" style="margin-left:6px">
          <?php if ($appName !== ''):
            $words = explode(' ', $appName);
            $last  = array_pop($words); ?>
            <?= !empty($words) ? htmlspecialchars(implode(' ', $words)) . ' ' : '' ?><span style="color:var(--coffee-light)"><?= htmlspecialchars($last) ?></span>
          <?php else: ?>Toko <span style="color:var(--coffee-light)">Kopi</span>
          <?php endif; ?>
        </span>
        <?php if ($tagline !== ''): ?>
        <div id="lp-tagline" style="font-size:.68rem;color:rgba(255,255,255,.45);margin-top:3px;font-weight:400">
          <?= htmlspecialchars($tagline) ?>
        </div>
        <?php else: ?>
        <div id="lp-tagline" style="font-size:.68rem;color:rgba(255,255,255,.45);margin-top:3px;font-weight:400;display:none"></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary" style="padding:11px 32px">
    💾 Simpan Tema & Branding
  </button>
  <span style="margin-left:12px;font-size:.82rem;color:var(--text-light)">
    Perubahan berlaku setelah halaman di-refresh.
  </span>
</form>

<script>
(function () {
  // ── Theme card selection ───────────────────────────────────

  var presets = <?= json_encode(array_map(fn($p) => [
    'label'   => $p['label'],
    'emoji'   => $p['emoji'],
    'dark'    => $p['vars']['--coffee-dark'],
  ], ThemesPlugin::PRESETS), JSON_UNESCAPED_UNICODE) ?>;

  document.querySelectorAll('.theme-card').forEach(function (card) {
    card.addEventListener('click', function () {
      // Update selected state
      document.querySelectorAll('.theme-card').forEach(function (c) {
        c.classList.remove('selected');
        var chk = c.querySelector('.theme-check');
        if (chk) { chk.remove(); }
      });
      card.classList.add('selected');
      var nameEl = card.querySelector('.theme-name');
      if (nameEl && !nameEl.querySelector('.theme-check')) {
        nameEl.insertAdjacentHTML('beforeend', '<span class="theme-check">✓</span>');
      }

      // Show/hide custom section
      var key = card.dataset.preset;
      var customSection = document.getElementById('custom-section');
      if (customSection) {
        customSection.style.display = key === 'custom' ? '' : 'none';
      }

      // Update preview banner
      if (key !== 'custom' && presets[key]) {
        var p = presets[key];
        document.getElementById('preview-banner').style.background = p.dark;
        document.getElementById('preview-emoji').textContent = p.emoji;
        document.getElementById('preview-name').textContent = p.label;
      } else if (key === 'custom') {
        var darkHex = document.getElementById('cc_dark').value || '#2C1A0E';
        document.getElementById('preview-banner').style.background = darkHex;
        document.getElementById('preview-emoji').textContent = '🎨';
        document.getElementById('preview-name').textContent = 'Custom';
      }
    });
  });

  // ── Custom color sync ─────────────────────────────────────

  window.syncColor = function (key, val) {
    var txt = document.getElementById('cc_' + key);
    if (txt) { txt.value = val; }
    // Update swatch in custom card if it's one of the 4 displayed
    var sw = document.getElementById('swatch-' + key);
    if (sw) { sw.style.background = val; }
    if (key === 'dark') {
      document.getElementById('preview-banner').style.background = val;
    }
  };

  window.syncColorFromText = function (key, val) {
    if (/^#[0-9a-fA-F]{6}$/.test(val)) {
      var picker = document.getElementById('cc_' + key + '_picker');
      if (picker) { picker.value = val; }
      var sw = document.getElementById('swatch-' + key);
      if (sw) { sw.style.background = val; }
      if (key === 'dark') {
        document.getElementById('preview-banner').style.background = val;
      }
    }
  };

  // ── Logo live preview ─────────────────────────────────────

  function updateLogoPreview() {
    var emoji   = document.getElementById('brand_emoji').value.trim() || '☕';
    var name    = document.getElementById('app_name').value.trim() || 'Toko Kopi';
    var tagline = document.getElementById('tagline').value.trim();

    document.getElementById('lp-emoji').textContent = emoji;

    // Split on last word for color highlight
    var words = name.split(' ');
    var last  = words.pop();
    var nameHtml = (words.length ? escH(words.join(' ')) + ' ' : '')
                 + '<span style="color:var(--coffee-light)">' + escH(last) + '</span>';
    document.getElementById('lp-name').innerHTML = nameHtml;

    var tagEl = document.getElementById('lp-tagline');
    if (tagEl) {
      tagEl.textContent = tagline;
      tagEl.style.display = tagline ? '' : 'none';
    }
  }

  function escH(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  ['brand_emoji','app_name','tagline'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) { el.addEventListener('input', updateLogoPreview); }
  });
})();
</script>
<?php
$content = ob_get_clean();
echo View::renderLayout('Tema & Branding', $content, 'super_admin');
