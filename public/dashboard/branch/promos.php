<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf, Sanitize, Currency};
use App\Models\BranchModel;
use App\Config\Database;

Auth::startSession();
Auth::requireLogin();

$user     = Auth::user();
$branchId = (int)$user['branch_id'];
if (!$branchId) exit('Access denied');

$db      = Database::getInstance();
$message = '';
$error   = '';
$currency = (new BranchModel())->getCurrency($branchId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $db->prepare(
            'INSERT INTO branch_promos (branch_id, title, description, discount_type, discount_value, min_order, max_discount, promo_code, start_date, end_date, auto_apply, min_tx_count, tx_months, applies_to_category_id, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)'
        )->execute([
            $branchId,
            Sanitize::string($_POST['title'] ?? ''),
            Sanitize::string($_POST['description'] ?? ''),
            in_array($_POST['discount_type'] ?? 'percent', ['percent','fixed']) ? $_POST['discount_type'] : 'percent',
            (float)($_POST['discount_value'] ?? 0),
            (float)($_POST['min_order'] ?? 0),
            !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null,
            Sanitize::string($_POST['promo_code'] ?? ''),
            !empty($_POST['start_date']) ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $_POST['start_date']))) : null,
            !empty($_POST['end_date'])   ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $_POST['end_date'])))   : null,
            isset($_POST['auto_apply']) ? 1 : 0,
            !empty($_POST['min_tx_count']) ? (int)$_POST['min_tx_count'] : null,
            !empty($_POST['tx_months'])    ? (int)$_POST['tx_months']    : null,
            !empty($_POST['applies_to_category_id']) ? (int)$_POST['applies_to_category_id'] : null,
        ]);
        $message = 'Promo ditambahkan.';
    } elseif ($action === 'edit') {
        $id     = (int)$_POST['promo_id'];
        $normDt = fn(string $v) => date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $v)));
        $own    = $db->prepare('SELECT id FROM branch_promos WHERE id = ? AND branch_id = ? LIMIT 1');
        $own->execute([$id, $branchId]);
        if ($own->fetch()) {
            $db->prepare(
                'UPDATE branch_promos
                 SET title=?, description=?, discount_type=?, discount_value=?,
                     min_order=?, max_discount=?, promo_code=?, start_date=?, end_date=?,
                     auto_apply=?, min_tx_count=?, tx_months=?, applies_to_category_id=?
                 WHERE id = ? AND branch_id = ?'
            )->execute([
                Sanitize::string($_POST['title']       ?? ''),
                Sanitize::string($_POST['description'] ?? ''),
                in_array($_POST['discount_type'] ?? 'percent', ['percent','fixed']) ? $_POST['discount_type'] : 'percent',
                (float)($_POST['discount_value'] ?? 0),
                (float)($_POST['min_order']      ?? 0),
                !empty($_POST['max_discount'])          ? (float)$_POST['max_discount'] : null,
                Sanitize::string($_POST['promo_code']  ?? ''),
                !empty($_POST['start_date']) ? $normDt($_POST['start_date']) : null,
                !empty($_POST['end_date'])   ? $normDt($_POST['end_date'])   : null,
                isset($_POST['auto_apply']) ? 1 : 0,
                !empty($_POST['min_tx_count']) ? (int)$_POST['min_tx_count'] : null,
                !empty($_POST['tx_months'])    ? (int)$_POST['tx_months']    : null,
                !empty($_POST['applies_to_category_id']) ? (int)$_POST['applies_to_category_id'] : null,
                $id, $branchId,
            ]);
            $message = 'Promo diperbarui.';
        }

    } elseif ($action === 'toggle') {
        $id  = (int)$_POST['promo_id'];
        $cur = $db->prepare('SELECT is_active FROM branch_promos WHERE id = ? AND branch_id = ? LIMIT 1');
        $cur->execute([$id, $branchId]);
        $promo = $cur->fetch();
        if ($promo) {
            $db->prepare('UPDATE branch_promos SET is_active = ? WHERE id = ?')->execute([$promo['is_active'] ? 0 : 1, $id]);
            $message = 'Status promo diperbarui.';
        }
    }
}

$stmt   = $db->prepare('SELECT * FROM branch_promos WHERE branch_id = ? ORDER BY created_at DESC');
$stmt->execute([$branchId]);
$promos = $stmt->fetchAll();

$categories = $db->query('SELECT id, name FROM menu_categories WHERE is_active=1 ORDER BY sort_order, name')->fetchAll();

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="section-header">
  <h2>Promo Cabang</h2>
  <div style="display:flex;gap:10px">
    <button class="btn btn-outline" onclick="document.getElementById('uploadPromoModal').classList.remove('hidden')">📤 Upload CSV</button>
    <button class="btn btn-primary" onclick="document.getElementById('addPromoModal').classList.remove('hidden')">+ Tambah Promo</button>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Judul</th><th>Tipe</th><th>Diskon</th><th>Kode</th><th>Periode</th><th>Kondisi</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
      <?php foreach ($promos as $p): ?>
      <tr>
        <td>
          <strong><?= htmlspecialchars($p['title']) ?></strong>
          <?php if ($p['promo_id']): ?>
            <br><span class="badge badge-orange" style="font-size:.7rem">dari Global</span>
          <?php endif; ?>
          <?php if ($p['description']): ?><br><small style="color:var(--text-light)"><?= htmlspecialchars(substr($p['description'],0,50)) ?></small><?php endif; ?>
        </td>
        <td><span class="badge badge-blue"><?= $p['discount_type'] ?></span></td>
        <td><?= $p['discount_type']==='percent' ? $p['discount_value'].'%' : Currency::format((float)$p['discount_value'], $currency) ?></td>
        <td><?= $p['promo_code'] ? '<code>'.$p['promo_code'].'</code>' : '—' ?></td>
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
        <td><span class="badge <?= $p['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= $p['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
        <td style="white-space:nowrap">
          <button class="btn btn-xs btn-outline" onclick='openEditModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)'>Edit</button>
          <form method="POST" style="display:inline">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="promo_id" value="<?= $p['id'] ?>">
            <?php
              if ($p['promo_id']) {
                  $btnLabel = $p['is_active'] ? 'Opt-out' : 'Aktifkan kembali';
              } else {
                  $btnLabel = $p['is_active'] ? 'Nonaktifkan' : 'Aktifkan';
              }
            ?>
            <button class="btn btn-xs btn-outline" type="submit"><?= $btnLabel ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($promos)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--text-light)">Belum ada promo</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Promo Modal -->
<div id="addPromoModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Tambah Promo Baru</div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label class="form-label">Judul Promo *</label>
        <input type="text" name="title" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" class="form-control" rows="2"></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Tipe Diskon</label>
          <select name="discount_type" class="form-control">
            <option value="percent">Persen (%)</option>
            <option value="fixed"><?= htmlspecialchars(Currency::fieldLabel('Nominal', $currency)) ?></option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Nilai Diskon *</label>
          <input type="number" name="discount_value" class="form-control" min="0" step="0.01" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label"><?= htmlspecialchars(Currency::fieldLabel('Min. Order', $currency)) ?></label>
          <input type="number" name="min_order" class="form-control" min="0" value="0">
        </div>
        <div class="form-group">
          <label class="form-label"><?= htmlspecialchars(Currency::fieldLabel('Maks. Diskon', $currency)) ?></label>
          <input type="number" name="max_discount" class="form-control" min="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Kode Promo</label>
        <input type="text" name="promo_code" class="form-control" placeholder="PROMO20 (kosongkan jika auto-apply)">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Mulai (tanggal &amp; jam)</label>
          <input type="datetime-local" name="start_date" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Selesai (tanggal &amp; jam)</label>
          <input type="datetime-local" name="end_date" class="form-control">
        </div>
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:10px">
        <input type="checkbox" name="auto_apply" id="branch_add_auto_apply" value="1">
        <label for="branch_add_auto_apply" class="form-label" style="margin:0">Auto-apply otomatis saat checkout (tanpa perlu ketik kode)</label>
      </div>
      <hr style="margin:12px 0;border-color:var(--border)">
      <div style="font-size:.85rem;font-weight:600;color:var(--text-mid);margin-bottom:8px">Syarat Loyalitas Pelanggan (opsional)</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="branch_add_min_tx">Min. Transaksi</label>
          <input type="number" name="min_tx_count" id="branch_add_min_tx" class="form-control" min="1" placeholder="contoh: 5">
        </div>
        <div class="form-group">
          <label class="form-label" for="branch_add_tx_months">Dalam X Bulan Terakhir</label>
          <input type="number" name="tx_months" id="branch_add_tx_months" class="form-control" min="1" placeholder="contoh: 3">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="branch_add_cat">Berlaku untuk Kategori (opsional)</label>
        <select name="applies_to_category_id" id="branch_add_cat" class="form-control">
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

<!-- Edit Branch Promo Modal -->
<div id="editPromoModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Edit Promo</div>
    <div id="edit_global_note" style="display:none;font-size:.82rem;color:var(--text-mid);padding:8px 12px;background:var(--coffee-cream);border-radius:6px;margin-bottom:12px">
      ✏️ Ini adalah salinan promo global. Perubahan hanya berlaku untuk cabang kamu — promo global tidak terpengaruh.
    </div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="promo_id" id="edit_id">
      <div class="form-group">
        <label class="form-label">Judul Promo *</label>
        <input type="text" name="title" id="edit_title" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" id="edit_desc" class="form-control" rows="2"></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Tipe Diskon</label>
          <select name="discount_type" id="edit_type" class="form-control">
            <option value="percent">Persen (%)</option>
            <option value="fixed"><?= htmlspecialchars(Currency::fieldLabel('Nominal', $currency)) ?></option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Nilai Diskon *</label>
          <input type="number" name="discount_value" id="edit_value" class="form-control" min="0" step="0.01" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label"><?= htmlspecialchars(Currency::fieldLabel('Min. Order', $currency)) ?></label>
          <input type="number" name="min_order" id="edit_min" class="form-control" min="0">
        </div>
        <div class="form-group">
          <label class="form-label"><?= htmlspecialchars(Currency::fieldLabel('Maks. Diskon', $currency)) ?></label>
          <input type="number" name="max_discount" id="edit_max" class="form-control" min="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Kode Promo</label>
        <input type="text" name="promo_code" id="edit_code" class="form-control">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Mulai (tanggal &amp; jam)</label>
          <input type="datetime-local" name="start_date" id="edit_start" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Selesai (tanggal &amp; jam)</label>
          <input type="datetime-local" name="end_date" id="edit_end" class="form-control">
        </div>
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:10px">
        <input type="checkbox" name="auto_apply" id="edit_auto" value="1">
        <label for="edit_auto" class="form-label" style="margin:0">Auto-apply saat checkout</label>
      </div>
      <hr style="margin:12px 0;border-color:var(--border)">
      <div style="font-size:.85rem;font-weight:600;color:var(--text-mid);margin-bottom:8px">Syarat Loyalitas (opsional)</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Min. Transaksi</label>
          <input type="number" name="min_tx_count" id="edit_min_tx" class="form-control" min="1">
        </div>
        <div class="form-group">
          <label class="form-label">Dalam X Bulan Terakhir</label>
          <input type="number" name="tx_months" id="edit_tx_months" class="form-control" min="1">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Berlaku untuk Kategori (opsional)</label>
        <select name="applies_to_category_id" id="edit_cat" class="form-control">
          <option value="">— Semua kategori —</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('editPromoModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<script>
function toDtInput(s) { return s ? s.replace(' ', 'T').slice(0, 16) : ''; }
function openEditModal(p) {
    document.getElementById('edit_id').value         = p.id;
    document.getElementById('edit_title').value      = p.title;
    document.getElementById('edit_desc').value       = p.description || '';
    document.getElementById('edit_type').value       = p.discount_type;
    document.getElementById('edit_value').value      = p.discount_value;
    document.getElementById('edit_min').value        = p.min_order || 0;
    document.getElementById('edit_max').value        = p.max_discount || '';
    document.getElementById('edit_code').value       = p.promo_code || '';
    document.getElementById('edit_start').value      = toDtInput(p.start_date);
    document.getElementById('edit_end').value        = toDtInput(p.end_date);
    document.getElementById('edit_auto').checked     = !!parseInt(p.auto_apply);
    document.getElementById('edit_min_tx').value     = p.min_tx_count || '';
    document.getElementById('edit_tx_months').value  = p.tx_months || '';
    document.getElementById('edit_cat').value        = p.applies_to_category_id || '';
    document.getElementById('edit_global_note').style.display = p.promo_id ? 'block' : 'none';
    document.getElementById('editPromoModal').classList.remove('hidden');
}
</script>

<!-- Upload CSV Modal -->
<div id="uploadPromoModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Upload Promo (CSV)</div>
    <p style="font-size:.85rem;color:var(--text-mid);margin-bottom:12px">Format: <code>title, description, discount_type, discount_value, min_order, promo_code, start_date, end_date</code></p>
    <form method="POST" action="<?= BASE_URL ?>/api/upload/promo.php" enctype="multipart/form-data">
      <input type="hidden" name="branch_id" value="<?= $branchId ?>">
      <div class="form-group">
        <label class="form-label">File CSV</label>
        <input type="file" name="file" class="form-control" accept=".csv" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('uploadPromoModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Upload</button>
      </div>
    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
echo View::renderLayout('Promo Cabang', $content, 'branch_admin');
