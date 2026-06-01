<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};
use App\Plugin\PluginLoader;

Auth::startSession();
Auth::requireRole('super_admin');

$plugin = PluginLoader::get('burger-template');
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'seed') {
    if ($plugin && method_exists($plugin, 'resetAndSeed')) {
        $result = $plugin->resetAndSeed();
    } else {
        $result = ['success' => false, 'message' => 'Plugin burger-template tidak aktif atau method resetAndSeed tidak ditemukan.'];
    }
}

$categories = $plugin && method_exists($plugin, 'getCategories') ? $plugin->getCategories() : [];

ob_start();
?>
<div class="card">
    <h2 style="margin-bottom:12px">🍔 Burger Template</h2>

    <p style="margin-bottom:12px">
        Plugin template untuk kedai burger, fast food, dan restoran American-style.
        Menyediakan <strong>15 menu</strong> siap pakai dalam 3 kategori.
    </p>

    <?php if ($result !== null): ?>
        <div class="alert <?= $result['success'] ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:16px">
            <?= htmlspecialchars($result['message']) ?>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
        <div style="background:#f8f9fa;border-radius:8px;padding:16px">
            <div style="font-size:1.5rem;font-weight:700;color:#a0522d">15</div>
            <div style="font-size:.85rem;color:#666">Menu Sample</div>
        </div>
        <div style="background:#f8f9fa;border-radius:8px;padding:16px">
            <div style="font-size:1.5rem;font-weight:700;color:#a0522d">3</div>
            <div style="font-size:.85rem;color:#666">Kategori Menu</div>
        </div>
    </div>

    <h3 style="margin:0 0 10px;font-size:1rem;color:#333">Kategori Menu</h3>
    <ul style="margin:0 0 20px;padding-left:20px;line-height:2;font-size:.9rem">
        <?php foreach ($categories as [$name, , $desc]): ?>
            <li><strong><?= htmlspecialchars($name) ?></strong> — <span style="color:#666"><?= htmlspecialchars($desc) ?></span></li>
        <?php endforeach; ?>
    </ul>

    <h3 style="margin:0 0 10px;font-size:1rem;color:#333">Contoh Menu</h3>
    <ul style="line-height:1.9;margin-bottom:20px;font-size:.9rem">
        <li>Burger Classic Beef, Burger Ayam Crispy, Burger BBQ Smoky</li>
        <li>Burger Mushroom Swiss, Burger Mozarella Melt, Burger Spicy Crunch</li>
        <li>Burger Fish Fillet, Burger Veggie Patty, Burger Box Paket</li>
        <li>French Fries (Regular/Large), Onion Ring, Chicken Wings</li>
        <li>Es Teh, Soft Drink Cola, Milkshake Vanilla, Milkshake Coklat</li>
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
            lalu mengisi ulang dengan 15 menu burger. Proses ini tidak dapat dibatalkan.
        </div>
        <form method="POST" onsubmit="return confirm('Yakin reset dan seed 15 menu burger? Semua data produk dan pesanan akan dihapus.')">
            <input type="hidden" name="action" value="seed">
            <button type="submit" class="btn btn-primary">
                🍔 Reset & Seed Menu Burger (15 menu)
            </button>
        </form>
    <?php else: ?>
        <div class="alert alert-warning">
            Plugin <code>burger-template</code> belum aktif. Aktifkan melalui halaman
            <a href="<?= BASE_URL ?>/dashboard/super/plugins.php">Plugins</a> terlebih dahulu.
        </div>
    <?php endif; ?>
</div>
<?php

$content = ob_get_clean();
echo View::renderLayout('Burger Template', $content, 'super_admin');
