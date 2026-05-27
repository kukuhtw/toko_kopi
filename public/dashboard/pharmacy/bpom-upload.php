<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};

Auth::startSession();
Auth::requireRole('super_admin');

ob_start();
?>
<div class="card">
    <h2>📄 Upload CSV Obat BPOM</h2>

    <p>
        Upload file CSV daftar obat BPOM untuk import massal.
    </p>

    <div style="margin-top:20px;padding:16px;background:#fafafa;border:1px solid #ddd;border-radius:8px">
<pre>product_name,generic_name,bpom_no,dosage,manufacturer,requires_prescription,default_price</pre>
    </div>

    <form method="POST" enctype="multipart/form-data" style="margin-top:24px">
        <input type="file" name="bpom_csv" accept=".csv">

        <button type="submit" style="padding:10px 18px;margin-left:12px">
            Upload CSV
        </button>
    </form>

    <div style="margin-top:30px">
        <h3>Contoh CSV</h3>

<pre>
Paracetamol 500mg,Paracetamol,BPOM12345,500mg,Kimia Farma,0,12000
Amoxicillin 500mg,Amoxicillin,BPOM67890,500mg,Hexpharm,1,28000
</pre>
    </div>
</div>
<?php

$content = ob_get_clean();

echo View::renderLayout('Upload CSV BPOM', $content, 'super_admin');
