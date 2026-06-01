<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};
use App\Plugin\PluginLoader;

Auth::startSession();
Auth::requireRole('super_admin');

$plugin = PluginLoader::get('minimarket-template');
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'seed') {
    if ($plugin && method_exists($plugin, 'resetAndSeed')) {
        $result = $plugin->resetAndSeed();
    } else {
        $result = ['success' => false, 'message' => 'Plugin minimarket-template tidak aktif atau method resetAndSeed tidak ditemukan.'];
    }
}

$categories = [];
if ($plugin && method_exists($plugin, 'getCategories')) {
    $categories = $plugin->getCategories();
}

ob_start();
?>
<div class="card">
    <h2 style="margin-bottom:12px">🏪 Minimarket Template</h2>

    <p style="margin-bottom:12px">
        Plugin template untuk toko minimarket, convenience store, dan toko kelontong modern.
        Menyediakan 120 contoh produk dalam 12 kategori siap pakai.
    </p>

    <?php if ($result !== null): ?>
        <div class="alert <?= $result['success'] ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:16px">
            <?= htmlspecialchars($result['message']) ?>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
        <div style="background:#f8f9fa;border-radius:8px;padding:16px">
            <div style="font-size:1.5rem;font-weight:700;color:#2c7a2c">120</div>
            <div style="font-size:.85rem;color:#666">Produk Sample</div>
        </div>
        <div style="background:#f8f9fa;border-radius:8px;padding:16px">
            <div style="font-size:1.5rem;font-weight:700;color:#2c7a2c">12</div>
            <div style="font-size:.85rem;color:#666">Kategori Produk</div>
        </div>
    </div>

    <h3 style="margin:0 0 10px;font-size:1rem;color:#333">Kategori Produk</h3>
    <ul style="margin:0 0 20px;padding-left:20px;line-height:2;font-size:.9rem">
        <?php foreach ($categories as [$name, , $desc]): ?>
            <li><strong><?= htmlspecialchars($name) ?></strong> — <span style="color:#666"><?= htmlspecialchars($desc) ?></span></li>
        <?php endforeach; ?>
    </ul>

    <h3 style="margin:0 0 10px;font-size:1rem;color:#333">Fitur Template</h3>
    <ul style="line-height:1.9;margin-bottom:20px;font-size:.9rem">
        <li>120 produk minimarket nyata (bukan placeholder)</li>
        <li>Varian ukuran/kemasan per produk (eceran & grosir)</li>
        <li>Harga realistis dalam Rupiah</li>
        <li>Kategori: sembako, snack, minuman, frozen food, sabun, dll</li>
        <li>Mendukung multi cabang dengan override harga per cabang</li>
        <li>Siap dikombinasi plugin barcode scanner (minimarket-barcode)</li>
    </ul>

    <div style="padding:14px;border:1px solid #ddd;border-radius:8px;background:#fafafa;margin-bottom:20px">
        Status Plugin:
        <strong style="color:<?= $plugin ? '#2c7a2c' : '#c0392b' ?>">
            <?= $plugin ? 'Aktif' : 'Tidak Aktif' ?>
        </strong>
    </div>

    <?php if ($plugin): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:14px;margin-bottom:16px;font-size:.875rem">
            <strong>⚠️ Perhatian:</strong> Reset & Seed akan menghapus <strong>semua data produk, kategori, dan pesanan</strong> yang ada,
            lalu mengisi ulang dengan 120 produk minimarket. Proses ini tidak dapat dibatalkan.
        </div>
        <form method="POST" onsubmit="return confirm('Yakin ingin reset dan seed semua produk minimarket? Semua data produk dan pesanan akan dihapus.')">
            <input type="hidden" name="action" value="seed">
            <button type="submit" class="btn btn-primary">
                🏪 Reset & Seed Produk Minimarket (120 produk)
            </button>
        </form>
    <?php else: ?>
        <div class="alert alert-warning">
            Plugin <code>minimarket-template</code> belum aktif. Aktifkan melalui halaman
            <a href="<?= BASE_URL ?>/dashboard/super/plugins.php">Plugins</a> terlebih dahulu.
        </div>
    <?php endif; ?>
</div>
<?php

$content = ob_get_clean();

echo View::renderLayout('Minimarket Template', $content, 'super_admin');
