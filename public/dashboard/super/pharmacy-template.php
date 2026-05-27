<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};
use App\Plugin\PluginLoader;

Auth::startSession();
Auth::requireRole('super_admin');

$plugin = PluginLoader::get('pharmacy-template');

ob_start();
?>
<div class="card">
    <h2 style="margin-bottom:12px">💊 Pharmacy / Apotek Template</h2>

    <p>
        Plugin template usaha apotek dan farmasi.
    </p>

    <ul style="line-height:1.9">
        <li>12 kategori produk apotek</li>
        <li>120 produk sample</li>
        <li>Varian strip dan box</li>
        <li>Siap dikembangkan untuk stock inventory</li>
        <li>Mendukung multi cabang</li>
    </ul>

    <div style="margin-top:20px;padding:14px;border:1px solid #ddd;border-radius:8px;background:#fafafa">
        Status Plugin:
        <strong><?= $plugin ? 'Aktif' : 'Tidak Aktif' ?></strong>
    </div>
</div>
<?php

$content = ob_get_clean();

echo View::renderLayout('Pharmacy Template', $content, 'super_admin');
