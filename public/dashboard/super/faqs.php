<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\SpreadsheetTableService;

Auth::startSession();
Auth::requireRole('super_admin');

$repo = new FaqRepository();
$spreadsheet = new SpreadsheetTableService();
$message = '';
$editId = (int)($_GET['edit'] ?? 0);
$editFaq = $editId > 0 ? $repo->findGlobal($editId) : false;

if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'xls'], true)) {
    $rows = $repo->exportRows('global');
    if ($_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="faq-global.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['faq_id', 'scope', 'branch_id', 'parent_global_id', 'question', 'answer', 'tags', 'is_active', 'updated_at']);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    $xml = $spreadsheet->renderXmlWorkbook([
        [
            'name' => 'FAQ Global',
            'rows' => array_merge(
                [['faq_id', 'scope', 'branch_id', 'parent_global_id', 'question', 'answer', 'tags', 'is_active', 'updated_at']],
                array_map('array_values', $rows)
            ),
        ],
    ]);
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="faq-global.xls"');
    echo $xml;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_global_faq') {
        $faqId = (int)($_POST['faq_id'] ?? 0);
        $data = [
            'scope' => 'global',
            'branch_id' => null,
            'question' => trim((string)($_POST['question'] ?? '')),
            'answer' => trim((string)($_POST['answer'] ?? '')),
            'tags' => trim((string)($_POST['tags'] ?? '')),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($data['question'] !== '' && $data['answer'] !== '') {
            if ($faqId > 0 && $repo->findGlobal($faqId)) {
                $repo->updateEntry($faqId, $data);
                $message = 'FAQ global berhasil diperbarui.';
            } else {
                $repo->create($data);
                $message = 'FAQ global berhasil ditambahkan.';
            }
        }
    } elseif ($action === 'import_global_faq') {
        if (!empty($_FILES['faq_file']['tmp_name'])) {
            $tables = $spreadsheet->readTables((string)$_FILES['faq_file']['tmp_name'], (string)($_FILES['faq_file']['name'] ?? ''));
            $sheetRows = $tables[0]['rows'] ?? [];
            $header = array_map('trim', $sheetRows[0] ?? []);
            $importRows = [];
            foreach (array_slice($sheetRows, 1) as $sheetRow) {
                if (empty(array_filter($sheetRow, static fn($val) => trim((string)$val) !== ''))) {
                    continue;
                }
                $assoc = [];
                foreach ($header as $index => $column) {
                    $assoc[$column] = (string)($sheetRow[$index] ?? '');
                }
                $importRows[] = $assoc;
            }
            $summary = $repo->importRows('global', null, $importRows);
            $message = 'Import FAQ global selesai. Dibuat: ' . $summary['created'] . ', diupdate: ' . $summary['updated'] . '.';
        }
    } elseif ($action === 'toggle_global_faq') {
        $faqId = (int)($_POST['faq_id'] ?? 0);
        $row = $repo->findGlobal($faqId);
        if ($row) {
            $repo->toggleActive($faqId, !empty($_POST['activate']));
            $message = 'Status FAQ global berhasil diperbarui.';
        }
    } elseif ($action === 'rebuild_vectors') {
        $total = $repo->rebuildAllVectors();
        $message = 'Vector FAQ berhasil direbuild untuk ' . $total . ' entri.';
    }

    $editId = 0;
    $editFaq = false;
}

$faqs = $repo->getGlobalFaqs(true);

ob_start();
?>
<?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <div class="card-title">FAQ Global</div>
  <p style="font-size:.88rem;color:var(--text-light);margin-bottom:14px">
    FAQ global berlaku untuk semua cabang. Setiap perubahan otomatis memperbarui vector database lokal untuk retrieval FAQ.
  </p>
  <form method="POST" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="rebuild_vectors">
    <button type="submit" class="btn btn-outline">Rebuild Semua Vector FAQ</button>
    <a href="?export=csv" class="btn btn-outline">Export CSV</a>
    <a href="?export=xls" class="btn btn-outline">Export Excel</a>
  </form>
  <form method="POST" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-top:14px">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="import_global_faq">
    <div class="form-group" style="margin-bottom:0;min-width:320px">
      <label class="form-label">Import CSV / XLS / XLSX</label>
      <input type="file" name="faq_file" class="form-control" accept=".csv,.xls,.xlsx" required>
    </div>
    <button type="submit" class="btn btn-primary">Import FAQ Global</button>
  </form>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-title"><?= $editFaq ? 'Edit FAQ Global' : 'Tambah FAQ Global' ?></div>
  <form method="POST">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_global_faq">
    <input type="hidden" name="faq_id" value="<?= (int)($editFaq['id'] ?? 0) ?>">
    <div class="form-group">
      <label class="form-label">Pertanyaan</label>
      <input type="text" name="question" class="form-control" required value="<?= htmlspecialchars((string)($editFaq['question'] ?? '')) ?>" placeholder="Contoh: Apakah tersedia Wi-Fi?">
    </div>
    <div class="form-group">
      <label class="form-label">Jawaban</label>
      <textarea name="answer" class="form-control" rows="4" required placeholder="Jawaban FAQ yang akan dipakai chatbot dan dashboard."><?= htmlspecialchars((string)($editFaq['answer'] ?? '')) ?></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Tags</label>
        <input type="text" name="tags" class="form-control" value="<?= htmlspecialchars((string)($editFaq['tags'] ?? '')) ?>" placeholder="wifi, internet, dine in">
      </div>
      <div class="form-group" style="max-width:180px">
        <label class="form-label">Status</label>
        <label style="display:flex;align-items:center;gap:8px;margin-top:10px">
          <input type="checkbox" name="is_active" value="1" <?= !isset($editFaq['is_active']) || !empty($editFaq['is_active']) ? 'checked' : '' ?>>
          <span>Aktif</span>
        </label>
      </div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary"><?= $editFaq ? 'Update FAQ Global' : 'Tambah FAQ Global' ?></button>
      <?php if ($editFaq): ?>
        <a href="<?= BASE_URL ?>/dashboard/super/faqs.php" class="btn btn-outline">Batal Edit</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div style="display:flex;flex-direction:column;gap:16px">
  <?php foreach ($faqs as $faq): ?>
    <div class="card">
      <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
        <div>
          <div class="card-title" style="margin-bottom:6px"><?= htmlspecialchars((string)$faq['question']) ?></div>
          <div style="font-size:.8rem;color:var(--text-light)">
            Tags: <?= htmlspecialchars((string)($faq['tags'] ?: '-')) ?> | Update: <?= date('d/m/Y H:i', strtotime((string)$faq['updated_at'])) ?>
          </div>
        </div>
        <span class="badge <?= !empty($faq['is_active']) ? 'badge-green' : 'badge-gray' ?>"><?= !empty($faq['is_active']) ? 'Aktif' : 'Nonaktif' ?></span>
      </div>
      <div style="white-space:pre-line;margin-top:12px"><?= htmlspecialchars((string)$faq['answer']) ?></div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
        <a href="?edit=<?= (int)$faq['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
        <form method="POST">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="toggle_global_faq">
          <input type="hidden" name="faq_id" value="<?= (int)$faq['id'] ?>">
          <input type="hidden" name="activate" value="<?= !empty($faq['is_active']) ? '0' : '1' ?>">
          <button type="submit" class="btn btn-outline btn-sm"><?= !empty($faq['is_active']) ? 'Nonaktifkan' : 'Aktifkan' ?></button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (empty($faqs)): ?>
    <div class="card"><div style="color:var(--text-light)">Belum ada FAQ global.</div></div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
echo View::renderLayout('FAQ Global', $content, 'super_admin');
