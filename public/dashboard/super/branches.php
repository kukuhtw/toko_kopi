<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf, Sanitize};
use App\Models\BranchModel;
use App\Config\Database;

Auth::startSession();
Auth::requireRole('super_admin');

$branchModel = new BranchModel();
$message = '';
$error   = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = Sanitize::post('name');
        $slug  = Sanitize::slug($name);
        $city  = Sanitize::post('city');
        $postalCode = preg_replace('/\D/', '', (string)($_POST['postal_code'] ?? ''));
        $phone = Sanitize::post('phone');
        $email = Sanitize::post('email', 'email');
        $addr  = Sanitize::post('address');

        if (empty($name)) {
            $error = 'Nama cabang wajib diisi.';
        } else {
            $branchModel->insert([
                'name' => $name, 'slug' => $slug,
                'city' => $city, 'postal_code' => $postalCode, 'phone' => $phone,
                'email' => $email, 'address' => $addr,
            ]);
            $message = "Cabang '{$name}' berhasil ditambahkan.";
        }
    } elseif ($action === 'toggle') {
        $id  = (int) $_POST['branch_id'];
        $cur = $branchModel->find($id);
        if ($cur) {
            $branchModel->update($id, ['is_active' => $cur['is_active'] ? 0 : 1]);
            $message = 'Status cabang diperbarui.';
        }
    } elseif ($action === 'delete') {
        $id = (int) $_POST['branch_id'];
        $branchModel->delete($id);
        $message = 'Cabang dihapus.';
    }
}

$branches = $branchModel->getActive();

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="section-header">
  <h2>Daftar Cabang</h2>
  <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.remove('hidden')">
    + Tambah Cabang
  </button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Nama</th><th>Kota</th><th>Phone</th><th>Email</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
      <?php foreach ($branches as $b): ?>
      <tr>
        <td><?= $b['id'] ?></td>
        <td><strong><?= htmlspecialchars($b['name']) ?></strong><br><small style="color:var(--text-light)">/<?= htmlspecialchars($b['slug']) ?></small></td>
        <td><?= htmlspecialchars($b['city'] ?? '-') ?></td>
        <td><?= htmlspecialchars($b['phone'] ?? '-') ?></td>
        <td><?= htmlspecialchars($b['email'] ?? '-') ?></td>
        <td><span class="badge <?= $b['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= $b['is_active'] ? 'Aktif' : 'Non-aktif' ?></span></td>
        <td>
          <form method="POST" style="display:inline">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="branch_id" value="<?= $b['id'] ?>">
            <button class="btn btn-xs btn-outline" type="submit"><?= $b['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
          </form>
          <a href="<?= BASE_URL ?>/dashboard/super/branch-detail.php?id=<?= $b['id'] ?>" class="btn btn-xs btn-primary">Detail</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($branches)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--text-light)">Belum ada cabang</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Branch Modal -->
<div id="addModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Tambah Cabang Baru</div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nama Cabang *</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Kota</label>
          <input type="text" name="city" class="form-control">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Telepon</label>
          <input type="text" name="phone" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Kode Pos Cabang</label>
          <input type="text" name="postal_code" class="form-control">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Alamat</label>
        <textarea name="address" class="form-control" rows="2"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
echo View::renderLayout('Manajemen Cabang', $content, 'super_admin');
