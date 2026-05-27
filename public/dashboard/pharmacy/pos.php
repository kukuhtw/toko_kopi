<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};

Auth::startSession();
Auth::requireRole('super_admin');

ob_start();
?>
<div class="card">
    <h2>🧾 Pharmacy POS Cashier</h2>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-top:20px">
        <div>
            <input type="text" placeholder="Scan barcode / cari produk" style="width:100%;padding:12px;font-size:1rem">

            <table style="width:100%;margin-top:20px;border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="border:1px solid #ddd;padding:10px">Produk</th>
                        <th style="border:1px solid #ddd;padding:10px">Qty</th>
                        <th style="border:1px solid #ddd;padding:10px">Harga</th>
                        <th style="border:1px solid #ddd;padding:10px">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border:1px solid #ddd;padding:10px">Paracetamol 500mg</td>
                        <td style="border:1px solid #ddd;padding:10px">2</td>
                        <td style="border:1px solid #ddd;padding:10px">12000</td>
                        <td style="border:1px solid #ddd;padding:10px">24000</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="border:1px solid #ddd;border-radius:10px;padding:16px">
            <h3>Ringkasan</h3>

            <div style="margin-top:18px">Subtotal : Rp 24.000</div>
            <div style="margin-top:10px">Diskon : Rp 0</div>
            <div style="margin-top:10px;font-size:1.4rem;font-weight:bold">Grand Total : Rp 24.000</div>

            <button style="width:100%;margin-top:24px;padding:14px;background:#222;color:#fff;border:none;border-radius:8px">
                Bayar
            </button>
        </div>
    </div>
</div>
<?php

$content = ob_get_clean();

echo View::renderLayout('Pharmacy POS', $content, 'super_admin');
