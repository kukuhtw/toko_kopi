<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};

Auth::startSession();
Auth::requireRole('super_admin');

ob_start();
?>
<div class="card">
    <h2>📊 Pharmacy Analytics Dashboard</h2>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:24px">
        <div style="padding:18px;border:1px solid #ddd;border-radius:10px">
            <div style="font-size:2rem;font-weight:bold" id="totalProducts">0</div>
            <div>Total Products</div>
        </div>

        <div style="padding:18px;border:1px solid #ddd;border-radius:10px">
            <div style="font-size:2rem;font-weight:bold" id="todaySales">0</div>
            <div>Today Sales</div>
        </div>

        <div style="padding:18px;border:1px solid #ddd;border-radius:10px">
            <div style="font-size:2rem;font-weight:bold" id="lowStock">0</div>
            <div>Low Stock</div>
        </div>
    </div>

    <div style="margin-top:30px">
        <canvas id="salesChart" height="120"></canvas>
    </div>
</div>

<script>
async function loadAnalytics() {
    const response = await fetch('/public/api/pharmacy/analytics-summary.php');
    const result = await response.json();

    if (!result.success) {
        return;
    }

    document.getElementById('totalProducts').innerText = result.data.total_products;
    document.getElementById('todaySales').innerText = 'Rp ' + result.data.today_sales;
    document.getElementById('lowStock').innerText = result.data.low_stock;

    const canvas = document.getElementById('salesChart');
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = '#333';
    ctx.fillRect(10, 20, 60, 80);
    ctx.fillRect(90, 40, 60, 60);
    ctx.fillRect(170, 10, 60, 90);
    ctx.fillRect(250, 55, 60, 45);
}

loadAnalytics();
</script>
<?php

$content = ob_get_clean();

echo View::renderLayout('Pharmacy Analytics', $content, 'super_admin');
