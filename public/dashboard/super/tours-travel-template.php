<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};
use App\Plugin\PluginLoader;

Auth::startSession();
Auth::requireRole('super_admin');

$plugin = PluginLoader::get('tours-travel-template');
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'seed') {
    if ($plugin && method_exists($plugin, 'resetAndSeed')) {
        $result = $plugin->resetAndSeed();
    } else {
        $result = ['success' => false, 'message' => 'Plugin tours-travel-template tidak aktif atau method resetAndSeed tidak ditemukan.'];
    }
}

$categories = $plugin && method_exists($plugin, 'getCategories') ? $plugin->getCategories() : [];

ob_start();
?>
<div class="card">
    <h2 style="margin-bottom:12px">✈️ Tours &amp; Travel Template</h2>

    <p style="margin-bottom:12px">
        Template untuk agen wisata, open trip, tour leader, dan travel consultant.
        Menyediakan <strong>15 layanan</strong> dalam 3 kategori utama.
    </p>

    <?php if ($result !== null): ?>
        <div class="alert <?= $result['success'] ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:16px">
            <?= htmlspecialchars($result['message']) ?>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
        <div style="background:#f8f9fa;border-radius:8px;padding:16px">
            <div style="font-size:1.5rem;font-weight:700;color:#a0522d">15</div>
            <div style="font-size:.85rem;color:#666">Layanan Wisata</div>
        </div>
        <div style="background:#f8f9fa;border-radius:8px;padding:16px">
            <div style="font-size:1.5rem;font-weight:700;color:#a0522d">3</div>
            <div style="font-size:.85rem;color:#666">Kategori</div>
        </div>
    </div>

    <h3 style="margin:0 0 10px;font-size:1rem;color:#333">Kategori</h3>
    <ul style="margin:0 0 20px;padding-left:20px;line-height:2;font-size:.9rem">
        <?php foreach ($categories as [$name, , $desc]): ?>
            <li><strong><?= htmlspecialchars($name) ?></strong> — <span style="color:#666"><?= htmlspecialchars($desc) ?></span></li>
        <?php endforeach; ?>
    </ul>

    <h3 style="margin:0 0 10px;font-size:1rem;color:#333">Contoh Layanan</h3>
    <ul style="line-height:1.9;margin-bottom:20px;font-size:.9rem">
        <li>Bali 3D2N, Jogja Heritage, Labuan Bajo Sailing, Bromo Ijen, Lombok Gili</li>
        <li>Singapore, Kuala Lumpur, Bangkok, Japan Sakura, Turki Cappadocia</li>
        <li>Visa, Airport Transfer, Asuransi, Upgrade Hotel, Private Guide</li>
    </ul>

    <div style="padding:14px;border:1px solid #ddd;border-radius:8px;background:#fafafa;margin-bottom:20px">
        Status Plugin:
        <strong style="color:<?= $plugin ? '#2c7a2c' : '#c0392b' ?>">
            <?= $plugin ? 'Aktif' : 'Tidak Aktif' ?>
        </strong>
    </div>

    <?php if ($plugin): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:14px;margin-bottom:16px;font-size:.875rem">
            <strong>⚠️ Perhatian:</strong> Reset &amp; Seed akan menghapus <strong>semua data produk, kategori, dan pesanan</strong>,
            lalu menggantinya dengan 15 layanan tours &amp; travel.
        </div>
        <form method="POST" onsubmit="return confirm('Yakin reset dan seed 15 layanan tours & travel? Semua data produk dan pesanan akan dihapus.')">
            <input type="hidden" name="action" value="seed">
            <button type="submit" class="btn btn-primary">
                ✈️ Reset &amp; Seed Tours &amp; Travel (15 layanan)
            </button>
        </form>
    <?php else: ?>
        <div class="alert alert-warning">
            Plugin <code>tours-travel-template</code> belum aktif. Aktifkan melalui halaman
            <a href="<?= BASE_URL ?>/dashboard/super/plugins.php">Plugins</a> terlebih dahulu.
        </div>
    <?php endif; ?>
</div>
<?php

$content = ob_get_clean();
echo View::renderLayout('Tours & Travel Template', $content, 'super_admin');
