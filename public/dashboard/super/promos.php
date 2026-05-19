<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf, Sanitize, Currency};
use App\Config\Database;

Auth::startSession();
Auth::requireRole('super_admin');

$db      = Database::getInstance();
$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title    = Sanitize::string($_POST['title'] ?? '');
        $desc     = Sanitize::string($_POST['description'] ?? '');
        $type     = in_array($_POST['discount_type'] ?? '', ['percent','fixed']) ? $_POST['discount_type'] : 'percent';
        $value    = (float)($_POST['discount_value'] ?? 0);
        $minOrder = (float)($_POST['min_order'] ?? 0);
        $maxDisc  = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null;
        $code      = Sanitize::string($_POST['promo_code'] ?? '') ?: null;
        $normDt    = fn(string $v) => date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $v)));
        $start     = !empty($_POST['start_date']) ? $normDt($_POST['start_date']) : null;
        $end       = !empty($_POST['end_date'])   ? $normDt($_POST['end_date'])   : null;
        $autoApply = isset($_POST['auto_apply'])  ? 1 : 0;
        $minTx     = !empty($_POST['min_tx_count']) ? (int)$_POST['min_tx_count'] : null;
        $txMonths  = !empty($_POST['tx_months'])    ? (int)$_POST['tx_months']    : null;
        $catId     = !empty($_POST['applies_to_category_id']) ? (int)$_POST['applies_to_category_id'] : null;

        if (!$title || $value <= 0) {
            $error = 'Judul dan nilai diskon wajib diisi.';
        } else {
            try {
                $db->prepare(
                    'INSERT INTO promos (title, description, discount_type, discount_value, min_order, max_discount, promo_code, start_date, end_date, auto_apply, min_tx_count, tx_months, applies_to_category_id, is_active)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)'
                )->execute([$title, $desc, $type, $value, $minOrder, $maxDisc, $code, $start, $end, $autoApply, $minTx, $txMonths, $catId]);
                $message = "Promo '{$title}' ditambahkan.";
            } catch (\PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? 'Kode promo sudah digunakan.' : 'Gagal menyimpan promo.';
            }
        }

    } elseif ($action === 'edit') {
        $id       = (int)($_POST['promo_id'] ?? 0);
        $title    = Sanitize::string($_POST['title'] ?? '');
        $desc     = Sanitize::string($_POST['description'] ?? '');
        $type     = in_array($_POST['discount_type'] ?? '', ['percent','fixed']) ? $_POST['discount_type'] : 'percent';
        $value    = (float)($_POST['discount_value'] ?? 0);
        $minOrder = (float)($_POST['min_order'] ?? 0);
        $maxDisc  = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null;
        $code      = Sanitize::string($_POST['promo_code'] ?? '') ?: null;
        $normDt    = fn(string $v) => date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $v)));
        $start     = !empty($_POST['start_date']) ? $normDt($_POST['start_date']) : null;
        $end       = !empty($_POST['end_date'])   ? $normDt($_POST['end_date'])   : null;
        $autoApply = isset($_POST['auto_apply'])  ? 1 : 0;
        $minTx     = !empty($_POST['min_tx_count']) ? (int)$_POST['min_tx_count'] : null;
        $txMonths  = !empty($_POST['tx_months'])    ? (int)$_POST['tx_months']    : null;
        $catId     = !empty($_POST['applies_to_category_id']) ? (int)$_POST['applies_to_category_id'] : null;

        if (!$id || !$title || $value <= 0) {
            $error = 'Data tidak valid.';
        } else {
            try {
                $db->prepare(
                    'UPDATE promos SET title=?, description=?, discount_type=?, discount_value=?, min_order=?, max_discount=?, promo_code=?, start_date=?, end_date=?, auto_apply=?, min_tx_count=?, tx_months=?, applies_to_category_id=?
                     WHERE id=?'
                )->execute([$title, $desc, $type, $value, $minOrder, $maxDisc, $code, $start, $end, $autoApply, $minTx, $txMonths, $catId, $id]);
                $message = 'Promo diperbarui.';
            } catch (\PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? 'Kode promo sudah digunakan.' : 'Gagal memperbarui promo.';
            }
        }

    } elseif ($action === 'toggle') {
        $id  = (int)($_POST['promo_id'] ?? 0);
        $cur = $db->prepare('SELECT is_active FROM promos WHERE id = ? LIMIT 1');
        $cur->execute([$id]);
        $row = $cur->fetch();
        if ($row) {
            $db->prepare('UPDATE promos SET is_active = ? WHERE id = ?')->execute([$row['is_active'] ? 0 : 1, $id]);
            $message = 'Status promo diperbarui.';
        }

    } elseif ($action === 'copy_to_branch') {
        $promoId  = (int)($_POST['promo_id']  ?? 0);
        $branchId = (int)($_POST['branch_id'] ?? 0);
        if ($promoId && $branchId) {
            $p = $db->prepare('SELECT * FROM promos WHERE id = ? LIMIT 1');
            $p->execute([$promoId]);
            $src = $p->fetch();
            if ($src) {
                $exists = $db->prepare('SELECT id FROM branch_promos WHERE promo_id = ? AND branch_id = ? LIMIT 1');
                $exists->execute([$promoId, $branchId]);
                if ($exists->fetch()) {
                    $error = 'Promo ini sudah disalin ke cabang tersebut.';
                } else {
                    $db->prepare(
                        'INSERT INTO branch_promos
                         (branch_id, promo_id, title, description, discount_type, discount_value,
                          min_order, max_discount, promo_code, start_date, end_date,
                          auto_apply, min_tx_count, tx_months, applies_to_category_id, is_active)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)'
                    )->execute([
                        $branchId, $promoId,
                        $src['title'], $src['description'], $src['discount_type'], $src['discount_value'],
                        $src['min_order'], $src['max_discount'], $src['promo_code'],
                        $src['start_date'], $src['end_date'],
                        $src['auto_apply'], $src['min_tx_count'], $src['tx_months'],
                        $src['applies_to_category_id'],
                    ]);
                    $message = "Promo \"{$src['title']}\" berhasil disalin ke cabang.";
                }
            }
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['promo_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM promos WHERE id = ?')->execute([$id]);
            $message = 'Promo dihapus.';
        }
    }
}

$stmt = $db->prepare('SELECT * FROM promos ORDER BY created_at DESC');
$stmt->execute();
$promos = $stmt->fetchAll();

// Copy counts: how many branches have a copy of each global promo
$copyCounts = [];
foreach ($db->query('SELECT promo_id, COUNT(*) AS cnt FROM branch_promos WHERE promo_id IS NOT NULL GROUP BY promo_id')->fetchAll() as $row) {
    $copyCounts[(int)$row['promo_id']] = (int)$row['cnt'];
}

$categories = $db->query('SELECT id, name FROM menu_categories WHERE is_active=1 ORDER BY sort_order, name')->fetchAll();
$branches   = $db->query('SELECT id, name FROM branches WHERE is_active=1 ORDER BY name')->fetchAll();

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="section-header">
  <h2>Promo Global</h2>
  <button class="btn btn-primary" onclick="document.getElementById('addPromoModal').classList.remove('hidden')">+ Tambah Promo</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Judul</th><th>Tipe</th><th>Diskon</th><th>Min. Order</th>
          <th>Kode</th><th>Periode</th><th>Kondisi</th><th>Salinan</th><th>Status</th><th>Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($promos as $p): ?>
      <tr>
        <td>
          <strong><?= htmlspecialchars($p['title']) ?></strong>
          <?php if ($p['description']): ?>
            <br><small style="color:var(--text-light)"><?= htmlspecialchars(mb_substr($p['description'], 0, 60)) ?></small>
          <?php endif; ?>
        </td>
        <td><span class="badge badge-blue"><?= $p['discount_type'] === 'percent' ? 'Persen' : 'Nominal' ?></span></td>
        <td>
          <?php if ($p['discount_type'] === 'percent'): ?>
            <?= (float)$p['discount_value'] ?>%
            <?php if ($p['max_discount']): ?>
              <br><small style="color:var(--text-light)">maks <?= Currency::format((float)$p['max_discount'], 'IDR') ?> (global)</small>
            <?php endif; ?>
          <?php else: ?>
            <?= Currency::format((float)$p['discount_value'], 'IDR') ?>
          <?php endif; ?>
        </td>
        <td><?= $p['min_order'] > 0 ? Currency::format((float)$p['min_order'], 'IDR') : '—' ?></td>
        <td><?= $p['promo_code'] ? '<code>'.htmlspecialchars($p['promo_code']).'</code>' : '—' ?></td>
        <td style="font-size:.8rem;white-space:nowrap">
          <?php
            $fmtDt = fn(?string $d) => $d ? date('d/m/Y H:i', strtotime($d)) : '—';
            echo $fmtDt($p['start_date']);
            if ($p['end_date']) { echo '<br>s/d ' . $fmtDt($p['end_date']); }
          ?>
        </td>
        <td style="font-size:.8rem;line-height:1.6">
          <?php if ($p['auto_apply']): ?><span class="badge badge-blue">Auto-apply</span><br><?php endif; ?>
          <?php if ($p['min_tx_count'] && $p['tx_months']): ?>
            <span class="badge badge-orange">≥<?= $p['min_tx_count'] ?>x / <?= $p['tx_months'] ?>bln</span><br>
          <?php endif; ?>
          <?php if ($p['applies_to_category_id']): ?>
            <span class="badge badge-gray">Kat. #<?= $p['applies_to_category_id'] ?></span>
          <?php endif; ?>
          <?php if (!$p['auto_apply'] && !$p['min_tx_count'] && !$p['applies_to_category_id']): ?>—<?php endif; ?>
        </td>
        <td style="font-size:.8rem;text-align:center">
          <?php $cnt = $copyCounts[$p['id']] ?? 0; ?>
          <?php if ($cnt > 0): ?>
            <span class="badge badge-blue" title="<?= $cnt ?> cabang memiliki salinan promo ini"><?= $cnt ?> cabang</span>
          <?php else: ?>
            <span style="color:var(--text-light)">—</span>
          <?php endif; ?>
        </td>
        <td><span class="badge <?= $p['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= $p['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
        <td style="white-space:nowrap">
          <button class="btn btn-xs btn-outline" onclick='openEditModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)'>Edit</button>
          <button class="btn btn-xs btn-outline" onclick='openCopyModal(<?= $p["id"] ?>, <?= htmlspecialchars(json_encode($p["title"]), ENT_QUOTES) ?>)'>Salin ↗</button>
          <form method="POST" style="display:inline">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="promo_id" value="<?= $p['id'] ?>">
            <button class="btn btn-xs btn-outline" type="submit"><?= $p['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
          </form>
          <form method="POST" style="display:inline" onsubmit="return confirm('Hapus promo ini?')">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="promo_id" value="<?= $p['id'] ?>">
            <button class="btn btn-xs" style="background:var(--danger);color:#fff" type="submit">Hapus</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($promos)): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--text-light);padding:32px">Belum ada promo global</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div id="addPromoModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Tambah Promo Global</div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label class="form-label" for="add_title">Judul Promo *</label>
        <input type="text" name="title" id="add_title" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="add_desc">Deskripsi</label>
        <textarea name="description" id="add_desc" class="form-control" rows="2"></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="add_type">Tipe Diskon</label>
          <select name="discount_type" id="add_type" class="form-control">
            <option value="percent">Persen (%)</option>
            <option value="fixed"><?= htmlspecialchars(Currency::fieldLabel('Nominal', 'IDR', true)) ?></option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="add_value">Nilai Diskon *</label>
          <input type="number" name="discount_value" id="add_value" class="form-control" min="0" step="0.01" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="add_min"><?= htmlspecialchars(Currency::fieldLabel('Min. Order', 'IDR', true)) ?></label>
          <input type="number" name="min_order" id="add_min" class="form-control" min="0" value="0">
        </div>
        <div class="form-group">
          <label class="form-label" for="add_max"><?= htmlspecialchars(Currency::fieldLabel('Maks. Diskon', 'IDR', true)) ?></label>
          <input type="number" name="max_discount" id="add_max" class="form-control" min="0" placeholder="Opsional">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="add_code">Kode Promo</label>
        <input type="text" name="promo_code" id="add_code" class="form-control" placeholder="PROMO20 (opsional, kosongkan jika auto-apply)">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="add_start">Mulai (tanggal &amp; jam)</label>
          <input type="datetime-local" name="start_date" id="add_start" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label" for="add_end">Selesai (tanggal &amp; jam)</label>
          <input type="datetime-local" name="end_date" id="add_end" class="form-control">
        </div>
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:10px">
        <input type="checkbox" name="auto_apply" id="add_auto_apply" value="1">
        <label for="add_auto_apply" class="form-label" style="margin:0">Auto-apply otomatis saat checkout (tanpa perlu ketik kode)</label>
      </div>
      <hr style="margin:12px 0;border-color:var(--border)">
      <div style="font-size:.85rem;font-weight:600;color:var(--text-mid);margin-bottom:8px">Syarat Loyalitas Pelanggan (opsional)</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="add_min_tx">Min. Transaksi</label>
          <input type="number" name="min_tx_count" id="add_min_tx" class="form-control" min="1" placeholder="contoh: 5">
        </div>
        <div class="form-group">
          <label class="form-label" for="add_tx_months">Dalam X Bulan Terakhir</label>
          <input type="number" name="tx_months" id="add_tx_months" class="form-control" min="1" placeholder="contoh: 3">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="add_cat">Berlaku untuk Kategori (opsional)</label>
        <select name="applies_to_category_id" id="add_cat" class="form-control">
          <option value="">— Semua kategori —</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addPromoModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editPromoModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Edit Promo</div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="promo_id" id="edit_id">
      <div class="form-group">
        <label class="form-label" for="edit_title">Judul Promo *</label>
        <input type="text" name="title" id="edit_title" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="edit_desc">Deskripsi</label>
        <textarea name="description" id="edit_desc" class="form-control" rows="2"></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="edit_type">Tipe Diskon</label>
          <select name="discount_type" id="edit_type" class="form-control">
            <option value="percent">Persen (%)</option>
            <option value="fixed"><?= htmlspecialchars(Currency::fieldLabel('Nominal', 'IDR', true)) ?></option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_value">Nilai Diskon *</label>
          <input type="number" name="discount_value" id="edit_value" class="form-control" min="0" step="0.01" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="edit_min"><?= htmlspecialchars(Currency::fieldLabel('Min. Order', 'IDR', true)) ?></label>
          <input type="number" name="min_order" id="edit_min" class="form-control" min="0" value="0">
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_max"><?= htmlspecialchars(Currency::fieldLabel('Maks. Diskon', 'IDR', true)) ?></label>
          <input type="number" name="max_discount" id="edit_max" class="form-control" min="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="edit_code">Kode Promo</label>
        <input type="text" name="promo_code" id="edit_code" class="form-control">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="edit_start">Mulai (tanggal &amp; jam)</label>
          <input type="datetime-local" name="start_date" id="edit_start" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_end">Selesai (tanggal &amp; jam)</label>
          <input type="datetime-local" name="end_date" id="edit_end" class="form-control">
        </div>
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:10px">
        <input type="checkbox" name="auto_apply" id="edit_auto_apply" value="1">
        <label for="edit_auto_apply" class="form-label" style="margin:0">Auto-apply otomatis saat checkout</label>
      </div>
      <hr style="margin:12px 0;border-color:var(--border)">
      <div style="font-size:.85rem;font-weight:600;color:var(--text-mid);margin-bottom:8px">Syarat Loyalitas Pelanggan (opsional)</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="edit_min_tx">Min. Transaksi</label>
          <input type="number" name="min_tx_count" id="edit_min_tx" class="form-control" min="1" placeholder="contoh: 5">
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_tx_months">Dalam X Bulan Terakhir</label>
          <input type="number" name="tx_months" id="edit_tx_months" class="form-control" min="1" placeholder="contoh: 3">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="edit_cat">Berlaku untuk Kategori (opsional)</label>
        <select name="applies_to_category_id" id="edit_cat" class="form-control">
          <option value="">— Semua kategori —</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('editPromoModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Perbarui</button>
      </div>
    </form>
  </div>
</div>

<!-- Copy to Branch Modal -->
<div id="copyPromoModal" class="modal-overlay hidden">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-title">Salin Promo ke Cabang</div>
    <p style="font-size:.9rem;color:var(--text-mid);margin-bottom:16px">
      Promo: <strong id="copy_promo_title"></strong>
    </p>
    <p style="font-size:.82rem;color:var(--text-light);margin-bottom:12px;padding:10px;background:var(--coffee-cream);border-radius:6px">
      Setelah disalin, cabang yang bersangkutan dapat mengaktifkan, menonaktifkan, atau mengubah nilai promo secara mandiri.
      Promo global tidak lagi berlaku untuk cabang tersebut — branch copy yang mengontrol.
    </p>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="copy_to_branch">
      <input type="hidden" name="promo_id" id="copy_promo_id">
      <div class="form-group">
        <label class="form-label" for="copy_branch_id">Pilih Cabang Tujuan</label>
        <select name="branch_id" id="copy_branch_id" class="form-control" required>
          <option value="">— Pilih cabang —</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('copyPromoModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Salin ke Cabang</button>
      </div>
    </form>
  </div>
</div>

<script>
function toDtInput(s) { return s ? s.replace(' ', 'T').slice(0, 16) : ''; }
function openCopyModal(promoId, promoTitle) {
    document.getElementById('copy_promo_id').value        = promoId;
    document.getElementById('copy_promo_title').textContent = promoTitle;
    document.getElementById('copy_branch_id').value       = '';
    document.getElementById('copyPromoModal').classList.remove('hidden');
}
function openEditModal(p) {
    document.getElementById('edit_id').value           = p.id;
    document.getElementById('edit_title').value        = p.title;
    document.getElementById('edit_desc').value         = p.description || '';
    document.getElementById('edit_type').value         = p.discount_type;
    document.getElementById('edit_value').value        = p.discount_value;
    document.getElementById('edit_min').value          = p.min_order || 0;
    document.getElementById('edit_max').value          = p.max_discount || '';
    document.getElementById('edit_code').value         = p.promo_code || '';
    document.getElementById('edit_start').value        = toDtInput(p.start_date);
    document.getElementById('edit_end').value          = toDtInput(p.end_date);
    document.getElementById('edit_auto_apply').checked = !!parseInt(p.auto_apply);
    document.getElementById('edit_min_tx').value       = p.min_tx_count || '';
    document.getElementById('edit_tx_months').value    = p.tx_months || '';
    document.getElementById('edit_cat').value          = p.applies_to_category_id || '';
    document.getElementById('editPromoModal').classList.remove('hidden');
}
</script>

<?php
$content = ob_get_clean();
echo View::renderLayout('Promo Global', $content, 'super_admin');


