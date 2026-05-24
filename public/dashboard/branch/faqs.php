<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\SpreadsheetTableService;

Auth::startSession();
Auth::requireRole('branch_admin');

$user = Auth::user();
$branchId = (int)$user['branch_id'];
$repo = new FaqRepository();
$spreadsheet = new SpreadsheetTableService();
$message = '';
$editId = (int)($_GET['edit'] ?? 0);
$editFaq = $editId > 0 ? $repo->findBranch($editId, $branchId) : false;

if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'xls'], true)) {
    $rows = $repo->exportRows('branch', $branchId);
    if ($_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="faq-branch-' . $branchId . '.csv"');
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
            'name' => 'FAQ Branch',
            'rows' => array_merge(
                [['faq_id', 'scope', 'branch_id', 'parent_global_id', 'question', 'answer', 'tags', 'is_active', 'updated_at']],
                array_map('array_values', $rows)
            ),
        ],
    ]);
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="faq-branch-' . $branchId . '.xls"');
    echo $xml;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_branch_faq') {
        $faqId = (int)($_POST['faq_id'] ?? 0);
        $parentGlobalId = (int)($_POST['parent_global_id'] ?? 0);
        $data = [
            'scope' => 'branch',
            'branch_id' => $branchId,
            'parent_global_id' => $parentGlobalId > 0 ? $parentGlobalId : null,
            'question' => trim((string)($_POST['question'] ?? '')),
            'answer' => trim((string)($_POST['answer'] ?? '')),
            'tags' => trim((string)($_POST['tags'] ?? '')),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($data['question'] !== '' && $data['answer'] !== '') {
            if ($faqId > 0 && $repo->findBranch($faqId, $branchId)) {
                $repo->updateEntry($faqId, $data);
                $message = 'FAQ cabang berhasil diperbarui.';
            } else {
                $repo->create($data);
                $message = 'FAQ cabang berhasil ditambahkan.';
            }
        }
    } elseif ($action === 'import_branch_faq') {
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
            $summary = $repo->importRows('branch', $branchId, $importRows);
            $message = 'Import FAQ cabang selesai. Dibuat: ' . $summary['created'] . ', diupdate: ' . $summary['updated'] . '.';
        }
    } elseif ($action === 'toggle_branch_faq') {
        $faqId = (int)($_POST['faq_id'] ?? 0);
        $row = $repo->findBranch($faqId, $branchId);
        if ($row) {
            $repo->toggleActive($faqId, !empty($_POST['activate']));
            $message = 'Status FAQ cabang berhasil diperbarui.';
        }
    } elseif ($action === 'create_override') {
        $globalFaqId = (int)($_POST['global_faq_id'] ?? 0);
        $globalFaq = $repo->findGlobal($globalFaqId);
        if ($globalFaq) {
            $existing = $repo->findBranchOverrideForGlobal($branchId, $globalFaqId);
            if (!$existing) {
                $repo->create([
                    'scope' => 'branch',
                    'branch_id' => $branchId,
                    'parent_global_id' => $globalFaqId,
                    'question' => (string)$globalFaq['question'],
                    'answer' => (string)$globalFaq['answer'],
                    'tags' => (string)($globalFaq['tags'] ?? ''),
                    'is_active' => 1,
                ]);
                $message = 'Override FAQ global berhasil dibuat untuk cabang ini.';
            }
        }
    }

    $editFaq = false;
}

$branchFaqs = $repo->getBranchFaqs($branchId, true);
$globalFaqs = $repo->getGlobalFaqsWithBranchOverrideStatus($branchId);
$summary = $repo->getAnalyticsSummary($branchId);
$topFaqs = $repo->getTopAskedFaqs($branchId);
$topMisses = $repo->getTopUnmatchedQueries($branchId);

ob_start();
?>
<?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <div class="card-title">FAQ Cabang</div>
  <p style="font-size:.88rem;color:var(--text-light);margin-bottom:0">
    Tambahkan FAQ khusus cabang untuk hal seperti parkir, jam operasional lokal, aturan dine-in, atau info pickup. FAQ cabang akan diprioritaskan saat retrieval.
  </p>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
    <a href="?export=csv" class="btn btn-outline">Export CSV</a>
    <a href="?export=xls" class="btn btn-outline">Export Excel</a>
  </div>
  <form method="POST" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-top:14px">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="import_branch_faq">
    <div class="form-group" style="margin-bottom:0;min-width:320px">
      <label class="form-label">Import CSV / XLS / XLSX</label>
      <input type="file" name="faq_file" class="form-control" accept=".csv,.xls,.xlsx" required>
    </div>
    <button type="submit" class="btn btn-primary">Import FAQ Cabang</button>
  </form>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-title">Analytics FAQ</div>
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:16px">
    <div>
      <div style="font-size:.78rem;color:var(--text-light)">Total Pertanyaan FAQ</div>
      <div style="font-size:1.3rem;font-weight:700"><?= (int)($summary['total_questions'] ?? 0) ?></div>
    </div>
    <div>
      <div style="font-size:.78rem;color:var(--text-light)">Percakapan Unik</div>
      <div style="font-size:1.3rem;font-weight:700"><?= (int)($summary['unique_conversations'] ?? 0) ?></div>
    </div>
    <div>
      <div style="font-size:.78rem;color:var(--text-light)">FAQ Terpakai</div>
      <div style="font-size:1.3rem;font-weight:700"><?= (int)($summary['matched_faqs'] ?? 0) ?></div>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div>
      <div style="font-weight:700;margin-bottom:8px">FAQ Paling Sering Ditanya</div>
      <?php if (empty($topFaqs)): ?>
        <div style="color:var(--text-light)">Belum ada data pertanyaan FAQ.</div>
      <?php else: ?>
        <?php foreach ($topFaqs as $row): ?>
          <div style="padding:10px 0;border-bottom:1px solid var(--border)">
            <div style="font-weight:600"><?= htmlspecialchars((string)$row['question']) ?></div>
            <div style="font-size:.8rem;color:var(--text-light)">Ditanya <?= (int)$row['total_asked'] ?>x | Scope <?= htmlspecialchars((string)$row['scope']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div>
      <div style="font-weight:700;margin-bottom:8px">Query Belum Match</div>
      <?php if (empty($topMisses)): ?>
        <div style="color:var(--text-light)">Belum ada query FAQ yang gagal match.</div>
      <?php else: ?>
        <?php foreach ($topMisses as $row): ?>
          <div style="padding:10px 0;border-bottom:1px solid var(--border)">
            <div style="font-weight:600"><?= htmlspecialchars((string)$row['query_text']) ?></div>
            <div style="font-size:.8rem;color:var(--text-light)">Muncul <?= (int)$row['total_asked'] ?>x</div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-title"><?= $editFaq ? 'Edit FAQ Cabang' : 'Tambah FAQ Cabang' ?></div>
  <form method="POST">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_branch_faq">
    <input type="hidden" name="faq_id" value="<?= (int)($editFaq['id'] ?? 0) ?>">
    <input type="hidden" name="parent_global_id" value="<?= (int)($editFaq['parent_global_id'] ?? 0) ?>">
    <div class="form-group">
      <label class="form-label">Pertanyaan</label>
      <input type="text" name="question" class="form-control" required value="<?= htmlspecialchars((string)($editFaq['question'] ?? '')) ?>" placeholder="Contoh: Apakah cabang ini punya area parkir?">
    </div>
    <div class="form-group">
      <label class="form-label">Jawaban</label>
      <textarea name="answer" class="form-control" rows="4" required><?= htmlspecialchars((string)($editFaq['answer'] ?? '')) ?></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Tags</label>
        <input type="text" name="tags" class="form-control" value="<?= htmlspecialchars((string)($editFaq['tags'] ?? '')) ?>" placeholder="parkir, motor, mobil">
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
      <button type="submit" class="btn btn-primary"><?= $editFaq ? 'Update FAQ Cabang' : 'Tambah FAQ Cabang' ?></button>
      <?php if ($editFaq): ?>
        <a href="<?= BASE_URL ?>/dashboard/branch/faqs.php" class="btn btn-outline">Batal Edit</a>
      <?php endif; ?>
    </div>
    <?php if ($editFaq && !empty($editFaq['parent_global_id'])): ?>
      <div style="margin-top:10px;font-size:.82rem;color:var(--text-light)">Ini adalah override untuk FAQ global #<?= (int)$editFaq['parent_global_id'] ?>.</div>
    <?php endif; ?>
  </form>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-title">FAQ Custom Cabang</div>
  <?php if (empty($branchFaqs)): ?>
    <div style="color:var(--text-light)">Belum ada FAQ custom untuk cabang ini.</div>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:14px">
      <?php foreach ($branchFaqs as $faq): ?>
        <div style="border:1px solid var(--border);border-radius:12px;padding:14px;background:#fff">
          <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
            <div>
              <div style="font-weight:700"><?= htmlspecialchars((string)$faq['question']) ?></div>
              <div style="font-size:.8rem;color:var(--text-light)">Tags: <?= htmlspecialchars((string)($faq['tags'] ?: '-')) ?><?php if (!empty($faq['parent_global_id'])): ?> | Override FAQ global #<?= (int)$faq['parent_global_id'] ?><?php endif; ?></div>
            </div>
            <span class="badge <?= !empty($faq['is_active']) ? 'badge-green' : 'badge-gray' ?>"><?= !empty($faq['is_active']) ? 'Aktif' : 'Nonaktif' ?></span>
          </div>
          <div style="white-space:pre-line;margin-top:10px"><?= htmlspecialchars((string)$faq['answer']) ?></div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
            <a href="?edit=<?= (int)$faq['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
            <form method="POST">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="toggle_branch_faq">
              <input type="hidden" name="faq_id" value="<?= (int)$faq['id'] ?>">
              <input type="hidden" name="activate" value="<?= !empty($faq['is_active']) ? '0' : '1' ?>">
              <button type="submit" class="btn btn-outline btn-sm"><?= !empty($faq['is_active']) ? 'Nonaktifkan' : 'Aktifkan' ?></button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-title">FAQ Global dan Override Cabang</div>
  <?php if (empty($globalFaqs)): ?>
    <div style="color:var(--text-light)">Belum ada FAQ global aktif.</div>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px">
      <?php foreach ($globalFaqs as $faq): ?>
        <div style="border:1px dashed var(--border);border-radius:12px;padding:12px;background:#fafafa">
          <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
            <div>
              <div style="font-weight:700"><?= htmlspecialchars((string)$faq['question']) ?></div>
              <div style="font-size:.8rem;color:var(--text-light)">Global FAQ #<?= (int)$faq['id'] ?></div>
            </div>
            <?php if (!empty($faq['branch_override_id'])): ?>
              <span class="badge badge-blue">Override aktif</span>
            <?php endif; ?>
          </div>
          <div style="white-space:pre-line;margin-top:8px"><?= htmlspecialchars((string)$faq['answer']) ?></div>
          <?php if (!empty($faq['branch_override_id'])): ?>
            <div style="margin-top:10px;padding:10px;border-radius:10px;background:#eef7fb;border:1px solid #cfe8f3">
              <div style="font-weight:600;margin-bottom:4px">Jawaban override cabang</div>
              <div style="white-space:pre-line"><?= htmlspecialchars((string)$faq['branch_override_answer']) ?></div>
              <div style="margin-top:10px">
                <a href="?edit=<?= (int)$faq['branch_override_id'] ?>" class="btn btn-outline btn-sm">Edit Override</a>
              </div>
            </div>
          <?php else: ?>
            <form method="POST" style="margin-top:10px">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="create_override">
              <input type="hidden" name="global_faq_id" value="<?= (int)$faq['id'] ?>">
              <button type="submit" class="btn btn-outline btn-sm">Buat Override Cabang</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
echo View::renderLayout('FAQ Cabang', $content, 'branch_admin');
