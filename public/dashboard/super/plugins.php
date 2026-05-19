<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf};

Auth::startSession();
Auth::requireRole('super_admin');

$pluginsDir  = BASE_PATH . '/plugins';
$configFile  = $pluginsDir . '/plugins.json';
$message     = '';
$error       = '';

// ── Load plugins.json ────────────────────────────────────────────
function readPluginsJson(string $file): array
{
    if (!file_exists($file)) return [];
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function writePluginsJson(string $file, array $data): bool
{
    return (bool) file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function detectTemplateType(string $slug): ?string
{
    return match ($slug) {
        'example-plugin' => 'Template Plugin',
        'example-skill-plugin' => 'Template Skill',
        default => str_starts_with($slug, 'example-') ? 'Template Developer' : null,
    };
}

// ── Scan semua plugin tersedia di direktori /plugins ────────────
function scanPlugins(string $pluginsDir, array $config): array
{
    $result = [];
    if (!is_dir($pluginsDir)) return $result;

    foreach (new DirectoryIterator($pluginsDir) as $entry) {
        if (!$entry->isDir() || $entry->isDot()) continue;
        $slug      = $entry->getFilename();
        $entryFile = $pluginsDir . '/' . $slug . '/plugin.php';
        if (!file_exists($entryFile)) continue;

        // Load metadata without executing register()
        try {
            $meta = require $entryFile;
        } catch (\Throwable) {
            $meta = [];
        }

        if (!is_array($meta)) continue;

        $result[$slug] = [
            'slug'        => $slug,
            'name'        => $meta['name']        ?? $slug,
            'version'     => $meta['version']      ?? '—',
            'author'      => $meta['author']       ?? '—',
            'description' => $meta['description']  ?? '',
            'requires'    => $meta['requires']      ?? '—',
            'active'      => ($config[$slug]['active'] ?? false) === true,
            'template'    => detectTemplateType($slug),
        ];
    }

    uasort($result, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $result;
}

// ── Handle POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';
    $slug   = preg_replace('/[^a-z0-9\-]/', '', $_POST['slug'] ?? '');

    if ($action === 'toggle' && $slug !== '') {
        $config = readPluginsJson($configFile);
        $current = ($config[$slug]['active'] ?? false) === true;
        $config[$slug] = ['active' => !$current];

        if (writePluginsJson($configFile, $config)) {
            $message = $current
                ? "Plugin <strong>{$slug}</strong> dinonaktifkan."
                : "Plugin <strong>{$slug}</strong> diaktifkan.";
        } else {
            $error = 'Gagal menyimpan plugins.json — periksa permission file.';
        }
    }
}

$config  = readPluginsJson($configFile);
$plugins = scanPlugins($pluginsDir, $config);

ob_start();
?>

<div class="section-header">
  <h2>Plugin Manager</h2>
  <p style="font-size:.88rem;color:var(--text-light);margin:0">
    Plugin tersimpan di <code>/plugins</code>. Aktifkan/nonaktifkan tanpa restart server.
  </p>
</div>

<div class="card" style="margin-top:0">
  <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px">
    <span class="badge badge-gray">Template Plugin</span>
    <span class="badge badge-gray">Template Skill</span>
  </div>
  <p style="font-size:.86rem;color:var(--text-mid);margin:0;line-height:1.6">
    Badge template menandai starter yang bisa langsung di-clone developer lain.
    Gunakan <code>example-plugin</code> untuk hook/plugin umum dan
    <code>example-skill-plugin</code> untuk skill chatbot baru.
  </p>
</div>

<?php if ($message): ?>
<div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (empty($plugins)): ?>
<div class="card">
  <p style="color:var(--text-light);text-align:center;padding:24px 0">
    Belum ada plugin ditemukan di folder <code>/plugins</code>.<br>
    Buat folder baru lalu mulai dari <code>/plugins/example-plugin/</code> atau <code>/plugins/example-skill-plugin/</code>.
  </p>
</div>
<?php else: ?>

<div class="card" style="margin-top:0">
  <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <button type="button" class="btn btn-primary btn-sm js-plugin-filter" data-filter="all">Semua</button>
      <button type="button" class="btn btn-outline btn-sm js-plugin-filter" data-filter="active">Aktif</button>
      <button type="button" class="btn btn-outline btn-sm js-plugin-filter" data-filter="template">Template</button>
    </div>
    <label style="display:flex;align-items:center;gap:8px;min-width:min(100%,320px)">
      <span style="font-size:.82rem;color:var(--text-light);white-space:nowrap">Cari plugin</span>
      <input
        type="search"
        id="plugin-search"
        placeholder="Nama, slug, deskripsi..."
        style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:#fff;color:var(--text-dark);font-size:.88rem"
      >
    </label>
  </div>
  <div style="margin-top:10px">
    <div id="plugin-filter-summary" style="font-size:.82rem;color:var(--text-light)">
      Menampilkan semua plugin.
    </div>
  </div>
</div>

<div id="plugin-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px">
  <?php foreach ($plugins as $slug => $p): ?>
  <div
    class="card js-plugin-card"
    data-active="<?= $p['active'] ? '1' : '0' ?>"
    data-template="<?= !empty($p['template']) ? '1' : '0' ?>"
    data-search="<?= htmlspecialchars(strtolower(trim(($p['name'] ?? '') . ' ' . $slug . ' ' . ($p['description'] ?? '') . ' ' . ($p['author'] ?? '')))) ?>"
    style="margin:0;position:relative"
  >
    <!-- Status badge -->
    <div style="position:absolute;top:16px;right:16px;display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;max-width:180px">
      <?php if (!empty($p['template'])): ?>
      <span class="badge badge-gray"><?= htmlspecialchars($p['template']) ?></span>
      <?php endif; ?>
      <span class="badge <?= $p['active'] ? 'badge-green' : 'badge-gray' ?>">
        <?= $p['active'] ? 'Aktif' : 'Nonaktif' ?>
      </span>
    </div>

    <!-- Header -->
    <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px;padding-right:72px">
      <div style="font-size:2rem;line-height:1">🔌</div>
      <div>
        <div style="font-weight:700;font-size:1rem;color:var(--coffee-dark)"><?= htmlspecialchars($p['name']) ?></div>
        <div style="font-size:.78rem;color:var(--text-light)">
          v<?= htmlspecialchars($p['version']) ?> · <?= htmlspecialchars($p['author']) ?>
        </div>
      </div>
    </div>

    <!-- Description -->
    <?php if ($p['description']): ?>
    <p style="font-size:.85rem;color:var(--text-mid);margin:0 0 14px;line-height:1.6">
      <?= htmlspecialchars($p['description']) ?>
    </p>
    <?php endif; ?>

    <?php if (!empty($p['template'])): ?>
    <div style="font-size:.8rem;color:var(--text-mid);background:var(--bg-light);border-radius:8px;padding:10px 12px;margin:0 0 14px">
      Plugin ini ditandai sebagai starter untuk developer. Cocok dijadikan dasar saat menambah plugin atau skill baru.
    </div>
    <?php endif; ?>

    <!-- Meta -->
    <div style="font-size:.78rem;color:var(--text-light);margin-bottom:14px">
      Slug: <code style="background:var(--bg-light);padding:1px 5px;border-radius:3px"><?= htmlspecialchars($slug) ?></code>
      &nbsp;·&nbsp; Requires: <?= htmlspecialchars($p['requires']) ?>
    </div>

    <!-- Toggle action -->
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="toggle">
      <input type="hidden" name="slug"   value="<?= htmlspecialchars($slug) ?>">
      <button type="submit" class="btn <?= $p['active'] ? 'btn-outline' : 'btn-primary' ?> btn-sm">
        <?= $p['active'] ? '⏸ Nonaktifkan' : '▶ Aktifkan' ?>
      </button>
    </form>
  </div>
  <?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const buttons = Array.from(document.querySelectorAll('.js-plugin-filter'));
  const cards = Array.from(document.querySelectorAll('.js-plugin-card'));
  const summary = document.getElementById('plugin-filter-summary');
  const searchInput = document.getElementById('plugin-search');
  let activeFilter = 'all';

  function matchesFilter(card, filter) {
    if (filter === 'active') return card.dataset.active === '1';
    if (filter === 'template') return card.dataset.template === '1';
    return true;
  }

  function matchesSearch(card, keyword) {
    if (!keyword) return true;
    return (card.dataset.search || '').indexOf(keyword) !== -1;
  }

  function updateSummary(filter, keyword, visibleCount, totalCount) {
    if (!summary) return;
    const hasKeyword = keyword.length > 0;
    const keywordLabel = hasKeyword ? ' untuk kata kunci "' + keyword + '"' : '';

    if (filter === 'active') {
      summary.textContent = 'Menampilkan ' + visibleCount + ' dari ' + totalCount + ' plugin aktif' + keywordLabel + '.';
      return;
    }
    if (filter === 'template') {
      summary.textContent = 'Menampilkan ' + visibleCount + ' dari ' + totalCount + ' plugin template developer' + keywordLabel + '.';
      return;
    }
    if (hasKeyword) {
      summary.textContent = 'Menampilkan ' + visibleCount + ' dari ' + totalCount + ' plugin untuk kata kunci "' + keyword + '".';
      return;
    }
    summary.textContent = 'Menampilkan semua plugin.';
  }

  function applyFilter() {
    const keyword = ((searchInput && searchInput.value) || '').trim().toLowerCase();
    let visibleCount = 0;

    cards.forEach(function (card) {
      const visible = matchesFilter(card, activeFilter) && matchesSearch(card, keyword);
      card.style.display = visible ? '' : 'none';
      if (visible) visibleCount += 1;
    });

    buttons.forEach(function (button) {
      const isActive = button.dataset.filter === activeFilter;
      button.classList.toggle('btn-primary', isActive);
      button.classList.toggle('btn-outline', !isActive);
    });

    updateSummary(activeFilter, keyword, visibleCount, cards.length);
  }

  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      activeFilter = button.dataset.filter || 'all';
      applyFilter();
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      applyFilter();
    });
  }

  applyFilter();
});
</script>

<?php endif; ?>

<!-- Panduan singkat -->
<div class="card" style="margin-top:24px">
  <div class="card-title">📖 Cara Menambah Plugin</div>
  <ol style="font-size:.88rem;color:var(--text-mid);line-height:1.9;padding-left:18px;margin:0">
    <li>Buat folder baru di <code>/plugins/{nama-plugin}/</code></li>
    <li>Pilih template: <code>/plugins/example-plugin/</code> untuk plugin umum atau <code>/plugins/example-skill-plugin/</code> untuk skill chatbot</li>
    <li>Ganti nama class, metadata, dan daftarkan hook atau skill yang dibutuhkan di method <code>register()</code></li>
    <li>Klik <strong>Aktifkan</strong> di halaman ini — plugin langsung berjalan tanpa restart</li>
  </ol>
  <p style="font-size:.85rem;margin:12px 0 0">
    📄 Dokumentasi lengkap: <code>/docs/plugin-system.md</code>
  </p>
</div>

<?php
$content = ob_get_clean();
echo View::renderLayout('Plugin Manager', $content, 'super_admin');
