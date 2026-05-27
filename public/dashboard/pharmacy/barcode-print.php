<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};

Auth::startSession();
Auth::requireRole('super_admin');

ob_start();
?>
<style>
.barcode-grid {
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
    gap:20px;
}
.barcode-card {
    border:1px solid #000;
    padding:12px;
    text-align:center;
}
.barcode-lines {
    font-family:monospace;
    font-size:1.4rem;
    letter-spacing:2px;
    margin:12px 0;
}
</style>

<div class="card">
    <h2>🏷 Barcode Printing</h2>

    <div class="barcode-grid">
        <div class="barcode-card">
            <div>Paracetamol 500mg</div>
            <div class="barcode-lines">|||| ||| ||||</div>
            <div>OBT-0001</div>
        </div>

        <div class="barcode-card">
            <div>Amoxicillin 500mg</div>
            <div class="barcode-lines">||| |||| |||</div>
            <div>OBT-0002</div>
        </div>
    </div>
</div>
<?php

$content = ob_get_clean();

echo View::renderLayout('Barcode Printing', $content, 'super_admin');
