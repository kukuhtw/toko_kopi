<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf};
use App\Plugin\PluginLoader;
use App\Models\BranchModel;

Auth::startSession();
Auth::requireRole('super_admin');

/** @var BakeryTemplatePlugin|null $plugin */
$plugin = PluginLoader::get('bakery-template');
if (!$plugin) {
    echo View::renderLayout(
        'Bakery Template',
        '<div class="card"><p style="color:var(--accent-red)">Plugin "bakery-template" tidak aktif. Aktifkan di halaman Plugins.</p></div>',
        'super_admin'
    );
    exit;
}

$message     = '';
$messageType = 'success';
$result      = null;

// Detect active branch currencies for display
$branchModel      = new BranchModel();
$activeBranches   = $branchModel->getActive();
$branchCurrencies = [];  // currency => count
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

// Category + item preview counts
$categories = BakeryTemplatePlugin::getCategories();
$menuItems  = BakeryTemplatePlugin::getMenuItems();
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
.bakery-warning-box {
  background: rgba(234,88,12,.08);
  border: 2px solid rgba(234,88,12,.4);
  border-radius: var(--radius-lg);
  padding: 20px 24px;
  margin-bottom: 24px;
}
.bakery-warning-box h3 {
  margin: 0 0 10px;
  color: #c2410c;
  font-size: 1rem;
  display: flex;
  align-items: center;
  gap: 8px;
}
.bakery-warning-box ul {
  margin: 0 0 0 18px;
  padding: 0;
  color: #9a3412;
  font-size: .88rem;
  line-height: 1.7;
}
.bakery-confirm-row {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(234,88,12,.06);
  border: 1.5px solid rgba(234,88,12,.3);
  border-radius: 8px;
  padding: 14px 16px;
  margin: 18px 0 0;
  cursor: pointer;
  user-select: none;
}
.bakery-confirm-row input[type=checkbox] {
  width: 18px;
  height: 18px;
  accent-color: #c2410c;
  flex-shrink: 0;
  cursor: pointer;
}
.bakery-confirm-row span {
  font-size: .9rem;
  font-weight: 600;
  color: #7c2d12;
}
.bakery-seed-btn {
  margin-top: 20px;
  padding: 12px 28px;
  background: #c2410c;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: .95rem;
  font-weight: 700;
  cursor: pointer;
  transition: background .15s, opacity .15s;
}
.bakery-seed-btn:disabled {
  opacity: .4;
  cursor: not-allowed;
}
.bakery-seed-btn:not(:disabled):hover { background: #9a3412; }

.bakery-preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 14px;
  margin-top: 0;
}
.bakery-cat-card {
  background: var(--coffee-white, #fdfaf7);
  border: 1px solid var(--border, #e0d5c9);
  border-radius: var(--radius-lg);
  padding: 16px;
}
.bakery-cat-card h4 {
  margin: 0 0 8px;
  font-size: .9rem;
  color: var(--text-dark);
}
.bakery-cat-card .bakery-item-list {
  list-style: none;
  margin: 0;
  padding: 0;
  font-size: .8rem;
  color: var(--text-mid);
  line-height: 1.8;
}
.bakery-cat-card .bakery-item-list li::before {
  content: '• ';
  color: var(--coffee-brown, #6f4e37);
}
.bakery-stat-row {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}
.bakery-stat {
  background: var(--coffee-white, #fdfaf7);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px 20px;
  text-align: center;
  min-width: 110px;
}
.bakery-stat .stat-num {
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--coffee-brown, #6f4e37);
}
.bakery-stat .stat-lbl {
  font-size: .75rem;
  color: var(--text-mid);
  margin-top: 2px;
}
.msg-success {
  background: rgba(34,197,94,.1);
  border: 1.5px solid rgba(34,197,94,.4);
  color: #15803d;
  border-radius: 8px;
  padding: 12px 16px;
  font-weight: 600;
  margin-bottom: 20px;
}
.msg-error {
  background: rgba(239,68,68,.1);
  border: 1.5px solid rgba(239,68,68,.4);
  color: #b91c1c;
  border-radius: 8px;
  padding: 12px 16px;
  font-weight: 600;
  margin-bottom: 20px;
}
</style>

<?php if ($message): ?>
<div class="<?= $messageType === 'success' ? 'msg-success' : 'msg-error' ?>">
  <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- ── Stats ───────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:24px">
  <h2 style="margin:0 0 16px;font-size:1.05rem">Ringkasan Template Bakery</h2>
  <div class="bakery-stat-row">
    <div class="bakery-stat">
      <div class="stat-num"><?= count($categories) ?></div>
      <div class="stat-lbl">Kategori</div>
    </div>
    <div class="bakery-stat">
      <div class="stat-num"><?= $totalItems ?></div>
      <div class="stat-lbl">Menu Item</div>
    </div>
    <div class="bakery-stat">
      <div class="stat-num"><?= $totalVariants ?></div>
      <div class="stat-lbl">Varian</div>
    </div>
    <?php foreach ($branchCurrencies as $cur => $count): ?>
    <div class="bakery-stat">
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

<!-- ── Preview per Category ───────────────────────────────── -->
<div class="card" style="margin-bottom:24px">
  <h2 style="margin:0 0 16px;font-size:1.05rem">Preview Menu</h2>
  <div class="bakery-preview-grid">
  <?php foreach ($categories as [$catName, $catSlug]): ?>
    <?php $items = $menuItems[$catSlug] ?? []; ?>
    <div class="bakery-cat-card">
      <h4><?= htmlspecialchars($catName) ?> <span style="font-weight:400;color:var(--text-mid)">(<?= count($items) ?>)</span></h4>
      <ul class="bakery-item-list">
        <?php foreach ($items as $item): ?>
        <li>
          <?= htmlspecialchars($item['name']) ?>
          <?php if (!empty($item['variants'])): ?>
            <span style="color:var(--coffee-medium,#a0724b);font-size:.75rem">
              (<?= implode(', ', array_map(fn($v) => $v[0], $item['variants'])) ?>)
            </span>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
  </div>
</div>

<!-- ── Reset & Seed Form ───────────────────────────────────── -->
<div class="card">
  <h2 style="margin:0 0 16px;font-size:1.05rem;color:#c2410c">Reset & Seed Data</h2>

  <div class="bakery-warning-box">
    <h3>&#9888; Peringatan — Aksi Ini Tidak Dapat Dibatalkan</h3>
    <ul>
      <li>Semua data <strong>menu_categories</strong>, <strong>menu_items</strong>, dan <strong>menu_item_variants</strong> akan dihapus permanen.</li>
      <li>Semua data <strong>branch_menu_overrides</strong> dan <strong>branch_menu_variant_overrides</strong> akan direset.</li>
      <li>Semua data <strong>orders</strong>, <strong>order_items</strong>, <strong>order_status_logs</strong>, <strong>carts</strong>, dan <strong>cart_items</strong> akan dihapus.</li>
      <li>Data akan diganti dengan <?= $totalItems ?> menu bakery baru beserta <?= $totalVariants ?> variannya.</li>
      <li>Pastikan Anda sudah backup database sebelum melanjutkan.</li>
    </ul>

    <label class="bakery-confirm-row" id="confirmLabel">
      <input type="checkbox" id="confirmCheck">
      <span>Saya mengerti bahwa semua data produk dan order akan direset secara permanen.</span>
    </label>
  </div>

  <form method="POST">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="reset_seed">
    <input type="hidden" name="i_understand" id="iUnderstand" value="0">

    <button type="submit" class="bakery-seed-btn" id="seedBtn" disabled style="margin-top:20px">
      Jalankan Reset &amp; Seed Template Bakery
    </button>
  </form>
</div>

<script>
(function () {
  const cb       = document.getElementById('confirmCheck');
  const hidden   = document.getElementById('iUnderstand');
  const btn      = document.getElementById('seedBtn');
  const label    = document.getElementById('confirmLabel');

  cb.addEventListener('change', function () {
    hidden.value = this.checked ? '1' : '0';
    btn.disabled = !this.checked;
    label.style.background = this.checked
      ? 'rgba(234,88,12,.12)'
      : 'rgba(234,88,12,.06)';
  });

  // Prevent accidental double-submit
  btn.closest('form').addEventListener('submit', function (e) {
    if (hidden.value !== '1') {
      e.preventDefault();
      alert('Centang konfirmasi terlebih dahulu.');
      return;
    }
    btn.disabled = true;
    btn.textContent = 'Memproses…';
  });
})();
</script>
<?php

$content = ob_get_clean();
echo View::renderLayout('Bakery Template', $content, 'super_admin');
