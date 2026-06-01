<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/Config/config.php';
use App\Helpers\{Auth, View};
use App\Plugin\PluginLoader;

Auth::startSession();
Auth::requireRole('super_admin');

$plugin = PluginLoader::get('hp-accessories-template');
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'seed') {
    $result = $plugin && method_exists($plugin, 'resetAndSeed')
        ? $plugin->resetAndSeed()
        : ['success' => false, 'message' => 'Plugin hp-accessories-template tidak aktif.'];
}

$categories = $plugin && method_exists($plugin, 'getCategories') ? $plugin->getCategories() : [];

ob_start(); ?>
<div class="card">
    <h2 style="margin-bottom:12px">📱 Toko Aksesori & Casing HP Template</h2>
    <p style="margin-bottom:12px">Plugin template untuk toko aksesori handphone, kedai casing, dan toko gadget. Menyediakan <strong>80 produk</strong> dalam 8 kategori.</p>

    <?php if ($result !== null): ?>
        <div class="alert <?= $result['success'] ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:16px">
            <?= htmlspecialchars($result['message']) ?>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
        <div style="background:#f8f9fa;border-radius:8px;padding:16px">
            <div style="font-size:1.5rem;font-weight:700;color:#a0522d">80</div>
            <div style="font-size:.85rem;color:#666">Produk Sample</div>
        </div>
        <div style="background:#f8f9fa;border-radius:8px;padding:16px">
            <div style="font-size:1.5rem;font-weight:700;color:#a0522d">8</div>
            <div style="font-size:.85rem;color:#666">Kategori Produk</div>
        </div>
    </div>

    <h3 style="margin:0 0 10px;font-size:1rem;color:#333">Kategori Produk</h3>
    <ul style="margin:0 0 20px;padding-left:20px;line-height:2;font-size:.9rem">
        <?php foreach ($categories as [$name, , $desc]): ?>
            <li><strong><?= htmlspecialchars($name) ?></strong> — <span style="color:#666"><?= htmlspecialchars($desc) ?></span></li>
        <?php endforeach; ?>
    </ul>

    <div style="padding:14px;border:1px solid #ddd;border-radius:8px;background:#fafafa;margin-bottom:20px">
        Status Plugin: <strong style="color:<?= $plugin ? '#2c7a2c' : '#c0392b' ?>"><?= $plugin ? 'Aktif' : 'Tidak Aktif' ?></strong>
    </div>

    <?php if ($plugin): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:14px;margin-bottom:16px;font-size:.875rem">
            <strong>⚠️ Perhatian:</strong> Reset & Seed akan menghapus <strong>semua data produk, kategori, dan pesanan</strong>, lalu mengisi 80 produk aksesori HP. Tidak dapat dibatalkan.
        </div>
        <form method="POST" onsubmit="return confirm('Yakin reset dan seed 80 produk aksesori HP?')">
            <input type="hidden" name="action" value="seed">
            <button type="submit" class="btn btn-primary">📱 Reset & Seed Aksesori HP (80 produk)</button>
        </form>
    <?php else: ?>
        <div class="alert alert-warning">Plugin <code>hp-accessories-template</code> belum aktif. Aktifkan di <a href="<?= BASE_URL ?>/dashboard/super/plugins.php">Plugins</a>.</div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
echo View::renderLayout('Aksesori HP Template', $content, 'super_admin');
