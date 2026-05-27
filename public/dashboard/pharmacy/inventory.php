<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};

Auth::startSession();
Auth::requireRole('super_admin');

ob_start();
?>
<div class="card">
    <h2>💊 Pharmacy Inventory Dashboard</h2>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:20px">
        <div style="padding:16px;border:1px solid #ddd;border-radius:10px">
            <div style="font-size:2rem;font-weight:bold">120</div>
            <div>Total Produk</div>
        </div>

        <div style="padding:16px;border:1px solid #ddd;border-radius:10px">
            <div style="font-size:2rem;font-weight:bold">34</div>
            <div>Low Stock Alert</div>
        </div>

        <div style="padding:16px;border:1px solid #ddd;border-radius:10px">
            <div style="font-size:2rem;font-weight:bold">12</div>
            <div>Expired &lt; 30 Hari</div>
        </div>

        <div style="padding:16px;border:1px solid #ddd;border-radius:10px">
            <div style="font-size:2rem;font-weight:bold">18</div>
            <div>Perlu Resep Dokter</div>
        </div>
    </div>

    <table style="width:100%;margin-top:24px;border-collapse:collapse">
        <thead>
            <tr>
                <th style="border:1px solid #ddd;padding:10px">SKU</th>
                <th style="border:1px solid #ddd;padding:10px">Produk</th>
                <th style="border:1px solid #ddd;padding:10px">Batch</th>
                <th style="border:1px solid #ddd;padding:10px">Expired</th>
                <th style="border:1px solid #ddd;padding:10px">Stock</th>
                <th style="border:1px solid #ddd;padding:10px">Minimum</th>
                <th style="border:1px solid #ddd;padding:10px">Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="border:1px solid #ddd;padding:10px">OBT-0001</td>
                <td style="border:1px solid #ddd;padding:10px">Paracetamol 500mg</td>
                <td style="border:1px solid #ddd;padding:10px">PCM2401A</td>
                <td style="border:1px solid #ddd;padding:10px">2027-01-01</td>
                <td style="border:1px solid #ddd;padding:10px">120</td>
                <td style="border:1px solid #ddd;padding:10px">20</td>
                <td style="border:1px solid #ddd;padding:10px;color:green">Normal</td>
            </tr>

            <tr>
                <td style="border:1px solid #ddd;padding:10px">OBT-0002</td>
                <td style="border:1px solid #ddd;padding:10px">Amoxicillin 500mg</td>
                <td style="border:1px solid #ddd;padding:10px">AMX2402B</td>
                <td style="border:1px solid #ddd;padding:10px">2026-07-01</td>
                <td style="border:1px solid #ddd;padding:10px">8</td>
                <td style="border:1px solid #ddd;padding:10px">20</td>
                <td style="border:1px solid #ddd;padding:10px;color:red">Low Stock</td>
            </tr>
        </tbody>
    </table>
</div>
<?php

$content = ob_get_clean();

echo View::renderLayout('Pharmacy Inventory', $content, 'super_admin');
