<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};

Auth::startSession();
Auth::requireRole('super_admin');

ob_start();
?>
<div class="card">
    <h2>🤖 AI Consultation Pharmacy</h2>

    <div style="padding:16px;background:#fff8e1;border:1px solid #f0d879;border-radius:10px;margin-top:20px">
        AI chatbot tidak menggantikan dokter atau apoteker profesional.
        Antibiotik, obat keras, dan obat penyakit kronis harus diverifikasi apoteker.
    </div>

    <div style="margin-top:24px">
        <textarea style="width:100%;height:140px;padding:14px" placeholder="Contoh: saya demam, sakit kepala, dan batuk sejak 2 hari"></textarea>

        <button style="margin-top:14px;padding:12px 20px">
            Analisa AI
        </button>
    </div>

    <div style="margin-top:30px;padding:18px;border:1px solid #ddd;border-radius:10px;background:#fafafa">
        <h3>Contoh Response AI</h3>

        <p>
            Gejala mengarah ke flu ringan atau infeksi saluran pernapasan atas ringan.
            Pastikan cukup istirahat dan hidrasi.
        </p>

        <p>
            Produk yang mungkin membantu:
        </p>

        <ul>
            <li>Paracetamol 500mg</li>
            <li>Vitamin C 1000mg</li>
            <li>Obat batuk herbal</li>
        </ul>

        <p style="color:#c62828;font-weight:bold">
            Bila demam tinggi lebih dari 3 hari atau sesak napas, segera konsultasi dokter.
        </p>
    </div>
</div>
<?php

$content = ob_get_clean();

echo View::renderLayout('AI Consultation Pharmacy', $content, 'super_admin');
