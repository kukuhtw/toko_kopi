<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf};
use App\Plugin\PluginLoader;
use App\Models\BranchModel;

Auth::startSession();
Auth::requireRole('super_admin');

/** @var RestoIndonesiaTemplatePlugin|null $plugin */
$plugin = PluginLoader::get('indonesian-resto-template');
if (!$plugin) {
    echo View::renderLayout(
        'Resto Indonesia Template',
        '<div class="card"><p style="color:var(--accent-red)">Plugin "indonesian-resto-template" tidak aktif. Aktifkan di halaman <a href="' . BASE_URL . '/dashboard/super/plugins.php">Plugins</a>.</p></div>',
        'super_admin'
    );
    exit;
}

$message     = '';
$messageType = 'success';

$branchModel      = new BranchModel();
$activeBranches   = $branchModel->getActive();
$branchCurrencies = [];
foreach ($activeBranches as $b) {
    $cur = $branchModel->getCurrency((int)$b['id']);
    $branchCurrencies[$cur] = ($branchCurrencies[$cur] ?? 0) + 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();

    $confirmed = (string)($_POST['i_understand'] ?? '') === '1';
    $action    = (string)($_POST['action'] ?? '');

    if ($action === 'reset_seed' && $confirmed) {
        $result = $plugin->resetAndSeed();
        if ($result['success']) {
            $ovrBranches = $result['override_branches'] ?? [];
            if (empty($ovrBranches)) {
                $ovrNote = 'Semua cabang menggunakan IDR.';
            } else {
                $parts = [];
                foreach ($ovrBranches as $bid => $cur) {
                    $parts[] = "Cabang #{$bid} ({$cur})";
                }
                $ovrNote = 'Override harga dibuat untuk: ' . implode(', ', $parts) . '.';
            }
            $message = sprintf(
                'Berhasil! %d kategori, %d menu, %d varian di-seed. %s',
                $result['categories'],
                $result['items'],
                $result['variants'],
                $ovrNote
            );
            $messageType = 'success';
        } else {
            $message     = 'Gagal: ' . htmlspecialchars($result['error'] ?? 'Unknown error');
            $messageType = 'error';
        }
    } elseif (!$confirmed) {
        $message     = 'Aksi dibatalkan. Centang konfirmasi terlebih dahulu.';
        $messageType = 'error';
    }
}

$categories    = RestoIndonesiaTemplatePlugin::getCategories();
$menuItems     = RestoIndonesiaTemplatePlugin::getMenuItems();
$totalItems    = 0;
$totalVariants = 0;
foreach ($menuItems as $items) {
    foreach ($items as $item) {
        $totalItems++;
        $totalVariants += count($item['variants']);
    }
}

ob_start();
?>
<style>
/* ── Warna tema merah-tanah Nusantara ────────────── */
:root {
  --ri-primary:   #c0392b;
  --ri-dark:      #922b21;
  --ri-accent:    #e67e22;
  --ri-light:     #fdf3f0;
  --ri-green:     #1a7a4a;
  --ri-warn-bg:   rgba(192,57,43,.07);
  --ri-warn-bd:   rgba(192,57,43,.35);
}

.ri-stat-row { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
.ri-stat {
  background: var(--ri-light);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px 20px;
  text-align: center;
  min-width: 110px;
}
.ri-stat .stat-num { font-size: 1.8rem; font-weight: 800; color: var(--ri-primary); }
.ri-stat .stat-lbl { font-size: .75rem; color: var(--text-mid); margin-top: 2px; }

.ri-preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 14px;
}
.ri-cat-card {
  background: var(--ri-light);
  border: 1px solid #e8d0cc;
  border-radius: var(--radius-lg);
  padding: 16px;
}
.ri-cat-card h4 { margin: 0 0 8px; font-size: .9rem; color: var(--ri-dark); }
.ri-item-list {
  list-style: none; margin: 0; padding: 0;
  font-size: .8rem; color: var(--text-mid); line-height: 1.8;
}
.ri-item-list li::before { content: '• '; color: var(--ri-accent); }
.ri-var-tags {
  display: inline-flex; flex-wrap: wrap; gap: 3px; margin-left: 4px;
}
.ri-var-tag {
  font-size: .65rem; background: rgba(192,57,43,.1); color: var(--ri-dark);
  padding: 1px 5px; border-radius: 4px; font-weight: 600;
}

.ri-warn-box {
  background: var(--ri-warn-bg);
  border: 2px solid var(--ri-warn-bd);
  border-radius: var(--radius-lg);
  padding: 20px 24px;
  margin-bottom: 24px;
}
.ri-warn-box h3 {
  margin: 0 0 10px;
  color: var(--ri-dark);
  font-size: 1rem;
  display: flex;
  align-items: center;
  gap: 8px;
}
.ri-warn-box ul {
  margin: 0 0 0 18px;
  padding: 0;
  color: var(--ri-dark);
  font-size: .88rem;
  line-height: 1.7;
}

.ri-confirm-row {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(230,126,34,.06);
  border: 1.5px solid rgba(230,126,34,.3);
  border-radius: 8px;
  padding: 14px 16px;
  margin: 18px 0 0;
  cursor: pointer;
  user-select: none;
}
.ri-confirm-row input[type=checkbox] {
  width: 18px; height: 18px;
  accent-color: var(--ri-primary);
  flex-shrink: 0; cursor: pointer;
}
.ri-confirm-row span { font-size: .9rem; font-weight: 600; color: #7c2d12; }

.ri-seed-btn {
  margin-top: 20px;
  padding: 12px 28px;
  background: var(--ri-primary);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: .95rem;
  font-weight: 700;
  cursor: pointer;
  transition: background .15s, opacity .15s;
}
.ri-seed-btn:disabled { opacity: .4; cursor: not-allowed; }
.ri-seed-btn:not(:disabled):hover { background: var(--ri-dark); }

.msg-success {
  background: rgba(26,122,74,.1); border: 1.5px solid rgba(26,122,74,.4);
  color: #14532d; border-radius: 8px; padding: 12px 16px;
  font-weight: 600; margin-bottom: 20px;
}
.msg-error {
  background: rgba(192,57,43,.1); border: 1.5px solid rgba(192,57,43,.4);
  color: var(--ri-dark); border-radius: 8px; padding: 12px 16px;
  font-weight: 600; margin-bottom: 20px;
}
</style>

<?php if ($message): ?>
<div class="<?= $messageType === 'success' ? 'msg-success' : 'msg-error' ?>">
  <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- ── Stats ── -->
<div class="card" style="margin-bottom:24px">
  <h2 style="margin:0 0 4px;font-size:1.05rem">Ringkasan Template Masakan Tradisional Indonesia</h2>
  <p style="margin:0 0 16px;font-size:.85rem;color:var(--text-light)">
    10 kategori · Nasi, Lauk, Soto, Mie, Sate, Pecel, Camilan, Sayuran, Minuman, Dessert
  </p>
  <div class="ri-stat-row">
    <div class="ri-stat">
      <div class="stat-num"><?= count($categories) ?></div>
      <div class="stat-lbl">Kategori</div>
    </div>
    <div class="ri-stat">
      <div class="stat-num"><?= $totalItems ?></div>
      <div class="stat-lbl">Menu Item</div>
    </div>
    <div class="ri-stat">
      <div class="stat-num"><?= $totalVariants ?></div>
      <div class="stat-lbl">Varian</div>
    </div>
    <div class="ri-stat">
      <div class="stat-num" style="font-size:1.1rem">Bumbu</div>
      <div class="stat-lbl">Original · Pedas · Extra Pedas</div>
    </div>
    <div class="ri-stat">
      <div class="stat-num" style="font-size:1.1rem">Ukuran</div>
      <div class="stat-lbl">Kecil · Normal · Besar</div>
    </div>
    <?php foreach ($branchCurrencies as $cur => $count): ?>
    <div class="ri-stat">
      <div class="stat-num" style="font-size:1.2rem"><?= htmlspecialchars($cur) ?></div>
      <div class="stat-lbl"><?= $count ?> cabang aktif</div>
    </div>
    <?php endforeach; ?>
  </div>
  <p style="margin:8px 0 0;font-size:.85rem;color:var(--text-mid)">
    Harga di-seed dalam <strong>IDR</strong> sebagai basis.
    Cabang dengan mata uang lain (USD, SGD, AUD) mendapat
    <em>branch price override</em> otomatis sesuai setting cabang.
  </p>
</div>

<!-- ── Preview per Category ── -->
<div class="card" style="margin-bottom:24px">
  <h2 style="margin:0 0 16px;font-size:1.05rem">Preview Menu per Kategori</h2>
  <div class="ri-preview-grid">
  <?php foreach ($categories as [$catName, $catSlug]): ?>
    <?php $items = $menuItems[$catSlug] ?? []; ?>
    <div class="ri-cat-card">
      <h4>
        <?= htmlspecialchars($catName) ?>
        <span style="font-weight:400;color:var(--text-mid)">(<?= count($items) ?>)</span>
      </h4>
      <ul class="ri-item-list">
        <?php foreach ($items as $item): ?>
        <li>
          <?= htmlspecialchars($item['name']) ?>
          <?php if (!empty($item['variants'])): ?>
          <span class="ri-var-tags">
            <?php foreach ($item['variants'] as $v): ?>
            <span class="ri-var-tag"><?= htmlspecialchars($v[0]) ?></span>
            <?php endforeach; ?>
          </span>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
  </div>
</div>

<!-- ── Reset & Seed ── -->
<div class="card">
  <h2 style="margin:0 0 16px;font-size:1.05rem;color:var(--ri-dark)">Reset &amp; Seed Data</h2>

  <div class="ri-warn-box">
    <h3>&#9888; Peringatan — Aksi Ini Tidak Dapat Dibatalkan</h3>
    <ul>
      <li>Semua data <strong>menu_categories</strong>, <strong>menu_items</strong>, dan <strong>menu_item_variants</strong> akan dihapus permanen.</li>
      <li>Semua data <strong>branch_menu_overrides</strong> dan <strong>branch_menu_variant_overrides</strong> akan direset.</li>
      <li>Semua data <strong>orders</strong>, <strong>order_items</strong>, <strong>order_status_logs</strong>, <strong>carts</strong>, dan <strong>cart_items</strong> akan dihapus.</li>
      <li>Data akan diganti dengan <strong><?= $totalItems ?> menu</strong> masakan tradisional Indonesia beserta <strong><?= $totalVariants ?> variannya</strong>.</li>
      <li>Cabang non-IDR akan mendapat <em>price override</em> otomatis sesuai mata uang cabang.</li>
      <li>Pastikan Anda sudah <strong>backup database</strong> sebelum melanjutkan.</li>
    </ul>

    <label class="ri-confirm-row" id="confirmLabel">
      <input type="checkbox" id="confirmCheck">
      <span>Saya mengerti bahwa semua data produk dan order akan direset secara permanen.</span>
    </label>
  </div>

  <form method="POST">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="reset_seed">
    <input type="hidden" name="i_understand" id="iUnderstand" value="0">

    <button type="submit" class="ri-seed-btn" id="seedBtn" disabled>
      Jalankan Reset &amp; Seed Template Masakan Tradisional Indonesia
    </button>
  </form>
</div>

<script>
(function () {
  const cb     = document.getElementById('confirmCheck');
  const hidden = document.getElementById('iUnderstand');
  const btn    = document.getElementById('seedBtn');
  const label  = document.getElementById('confirmLabel');

  cb.addEventListener('change', function () {
    hidden.value = this.checked ? '1' : '0';
    btn.disabled = !this.checked;
    label.style.background = this.checked
      ? 'rgba(230,126,34,.14)'
      : 'rgba(230,126,34,.06)';
  });

  btn.closest('form').addEventListener('submit', function (e) {
    if (hidden.value !== '1') {
      e.preventDefault();
      alert('Centang konfirmasi terlebih dahulu.');
      return;
    }
    btn.disabled    = true;
    btn.textContent = 'Memproses…';
  });
})();
</script>

<?php
$content = ob_get_clean();
echo View::renderLayout('Resto Indonesia Template', $content, 'super_admin');
