<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf, Sanitize};
use App\Models\{UserModel, BranchModel};

Auth::startSession();
Auth::requireRole('super_admin');

$userModel   = new UserModel();
$branchModel = new BranchModel();
$branches    = $branchModel->getActive();
$message     = '';
$error       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name     = Sanitize::string($_POST['name'] ?? '');
        $email    = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $pass     = $_POST['password'] ?? '';
        $role     = in_array($_POST['role'], ['super_admin','branch_admin']) ? $_POST['role'] : 'branch_admin';
        $branchId = $role === 'branch_admin' ? (int)$_POST['branch_id'] : null;

        if (!$name || !$email || strlen($pass) < 6) {
            $error = 'Nama, email valid, dan password (min 6 karakter) wajib diisi.';
        } elseif ($userModel->findByEmail($email)) {
            $error = 'Email sudah terdaftar.';
        } else {
            $userModel->createUser($name, $email, $pass, $role, $branchId);
            $message = "User '{$name}' berhasil dibuat.";
        }
    } elseif ($action === 'toggle') {
        $id  = (int)$_POST['user_id'];
        $usr = $userModel->find($id);
        if ($usr && $usr['id'] !== (int)Auth::user()['id']) {
            $userModel->update($id, ['is_active' => $usr['is_active'] ? 0 : 1]);
            $message = 'Status user diperbarui.';
        }
    }
}

$users = $userModel->getAllAdmins();

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="section-header">
  <h2>Manajemen User</h2>
  <div style="display:flex;gap:10px">
    <a href="<?= BASE_URL ?>/api/export/users.php" class="btn btn-outline" style="font-size:.85rem">&#8595; Export CSV</a>
    <button class="btn btn-primary" onclick="document.getElementById('addUserModal').classList.remove('hidden')">+ Tambah User</button>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Cabang</th><th>Status</th><th>Login Terakhir</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><span class="badge <?= $u['role']==='super_admin' ? 'badge-orange' : 'badge-blue' ?>"><?= str_replace('_',' ',$u['role']) ?></span></td>
        <td><?= htmlspecialchars($u['branch_name'] ?? '—') ?></td>
        <td><span class="badge <?= $u['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
        <td style="font-size:.8rem"><?= $u['last_login'] ? date('d/m/y H:i', strtotime($u['last_login'])) : '—' ?></td>
        <td>
          <?php if ((int)$u['id'] !== (int)Auth::user()['id']): ?>
          <form method="POST" style="display:inline">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
            <button class="btn btn-xs btn-outline" type="submit"><?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Tambah User Baru</div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nama *</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email *</label>
          <input type="email" name="email" class="form-control" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Password *</label>
          <input type="password" name="password" class="form-control" minlength="6" required>
        </div>
        <div class="form-group">
          <label class="form-label">Role *</label>
          <select name="role" class="form-control" id="userRole" onchange="toggleBranchField()">
            <option value="branch_admin">Branch Admin</option>
            <option value="super_admin">Super Admin</option>
          </select>
        </div>
      </div>
      <div class="form-group" id="branchField">
        <label class="form-label">Cabang *</label>
        <select name="branch_id" class="form-control">
          <option value="">Pilih cabang...</option>
          <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addUserModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Buat User</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleBranchField() {
  const role  = document.getElementById('userRole').value;
  const field = document.getElementById('branchField');
  field.style.display = role === 'branch_admin' ? 'block' : 'none';
}
</script>

<?php
$content = ob_get_clean();
echo View::renderLayout('Manajemen User', $content, 'super_admin');
