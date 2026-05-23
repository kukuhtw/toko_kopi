<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf, Sanitize, Currency};
use App\Models\{BranchModel, UserModel, OrderModel};
use App\Config\Database;

Auth::startSession();
Auth::requireRole('super_admin');

$branchId    = (int)($_GET['id'] ?? 0);
$branchModel = new BranchModel();
$branch      = $branchId ? $branchModel->find($branchId) : null;

if (!$branch) {
    header('Location: ' . BASE_URL . '/dashboard/super/branches.php');
    exit;
}

$db      = Database::getInstance();
$message = '';
$error   = '';
$currency = $branchModel->getCurrency($branchId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_branch') {
        $branchModel->update($branchId, [
            'name'    => Sanitize::string($_POST['name'] ?? ''),
            'city'    => Sanitize::string($_POST['city'] ?? ''),
            'postal_code' => preg_replace('/\D/', '', (string)($_POST['postal_code'] ?? '')),
            'phone'   => Sanitize::string($_POST['phone'] ?? ''),
            'email'   => Sanitize::email($_POST['email'] ?? '') ?: null,
            'address' => Sanitize::string($_POST['address'] ?? ''),
        ]);
        $branch  = $branchModel->find($branchId);
        $message = 'Info cabang diperbarui.';

    } elseif ($action === 'update_settings') {
        foreach (['currency', 'language', 'wa_number'] as $key) {
            if (isset($_POST[$key])) {
                $branchModel->setSetting($branchId, $key, Sanitize::string($_POST[$key]));
            }
        }
        $branchModel->setSetting(
            $branchId,
            'whatsapp_shared_inbox_enabled',
            isset($_POST['whatsapp_shared_inbox_enabled']) ? '1' : '0'
        );
        $message = 'Pengaturan cabang diperbarui.';

    } elseif ($action === 'add_admin') {
        $name     = Sanitize::string($_POST['admin_name'] ?? '');
        $email    = Sanitize::email($_POST['admin_email'] ?? '');
        $password = $_POST['admin_password'] ?? '';

        if (!$name || !$email || strlen($password) < 6) {
            $error = 'Nama, email, dan password (min 6 karakter) wajib diisi.';
        } else {
            $userModel = new UserModel();
            if ($userModel->findByEmail($email)) {
                $error = 'Email sudah terdaftar.';
            } else {
                $userModel->createUser($name, $email, $password, 'branch_admin', $branchId);
                $message = "Admin '{$name}' ditambahkan.";
            }
        }

    } elseif ($action === 'toggle_admin') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $user = $db->prepare('SELECT is_active FROM users WHERE id = ? AND branch_id = ? LIMIT 1');
        $user->execute([$uid, $branchId]);
        $row  = $user->fetch();
        if ($row) {
            $db->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$row['is_active'] ? 0 : 1, $uid]);
            $message = 'Status admin diperbarui.';
        }

    } elseif ($action === 'reset_password') {
        $uid      = (int)($_POST['user_id'] ?? 0);
        $password = $_POST['new_password'] ?? '';
        if (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $db->prepare('UPDATE users SET password = ? WHERE id = ? AND branch_id = ?')->execute([$hash, $uid, $branchId]);
            $message = 'Password admin direset.';
        }
    }
}

$settings = $branchModel->getAllSettings($branchId);
$userModel = new UserModel();
$admins    = $userModel->getByBranch($branchId);

$orderStmt = $db->prepare(
    'SELECT o.*, c.name AS customer_name
     FROM orders o
     LEFT JOIN customers c ON o.customer_id = c.id
     WHERE o.branch_id = ?
     ORDER BY o.created_at DESC
     LIMIT 10'
);
$orderStmt->execute([$branchId]);
$recentOrders = $orderStmt->fetchAll();

$statsStmt = $db->prepare(
    'SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_amount), 0) AS total_revenue,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS today_orders
     FROM orders WHERE branch_id = ?'
);
$statsStmt->execute([$branchId]);
$stats = $statsStmt->fetch();

ob_start();
?>
<div class="section-header">
  <div class="section-actions">
    <a href="<?= BASE_URL ?>/dashboard/super/branches.php" class="btn btn-outline btn-sm">&larr; Kembali</a>
    <h2 style="margin:0"><?= htmlspecialchars($branch['name']) ?></h2>
    <span class="badge <?= $branch['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= $branch['is_active'] ? 'Aktif' : 'Nonaktif' ?></span>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Stats row -->
<div class="dashboard-grid-3" style="margin-bottom:24px">
  <div class="stat-card orange">
    <div class="stat-label">Total Pendapatan</div>
    <div class="stat-value" style="font-size:1.5rem"><?= Currency::format((float)$stats['total_revenue'], $currency) ?></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-label">Total Order</div>
    <div class="stat-value"><?= $stats['total_orders'] ?></div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Order Hari Ini</div>
    <div class="stat-value"><?= $stats['today_orders'] ?></div>
  </div>
</div>

<div class="dashboard-grid-branch-detail">

  <!-- Branch Info -->
  <div class="card">
    <div class="card-title">🏪 Info Cabang</div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="update_branch">
      <div class="form-group">
        <label class="form-label" for="b_name">Nama Cabang</label>
        <input type="text" id="b_name" name="name" class="form-control" value="<?= htmlspecialchars($branch['name']) ?>" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="b_city">Kota</label>
          <input type="text" id="b_city" name="city" class="form-control" value="<?= htmlspecialchars($branch['city'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="b_postal_code">Kode Pos Cabang</label>
          <input type="text" id="b_postal_code" name="postal_code" class="form-control" value="<?= htmlspecialchars($branch['postal_code'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="b_phone">Telepon</label>
          <input type="text" id="b_phone" name="phone" class="form-control" value="<?= htmlspecialchars($branch['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="b_email">Email</label>
          <input type="email" id="b_email" name="email" class="form-control" value="<?= htmlspecialchars($branch['email'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="b_address">Alamat</label>
        <textarea id="b_address" name="address" class="form-control" rows="2"><?= htmlspecialchars($branch['address'] ?? '') ?></textarea>
      </div>
      <div style="text-align:right">
        <button type="submit" class="btn btn-primary">Simpan Info</button>
      </div>
    </form>
  </div>

  <!-- Branch Settings -->
  <div class="card">
    <div class="card-title">⚙️ Pengaturan Cabang</div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="update_settings">
      <div class="form-group">
        <label class="form-label" for="s_currency">Mata Uang</label>
        <select id="s_currency" name="currency" class="form-control">
          <?php foreach (['IDR','USD','SGD','AUD'] as $cur): ?>
            <option value="<?= $cur ?>" <?= ($settings['currency'] ?? 'IDR') === $cur ? 'selected' : '' ?>><?= $cur ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="s_language">Bahasa</label>
        <select id="s_language" name="language" class="form-control">
          <option value="id" <?= ($settings['language'] ?? 'id') === 'id' ? 'selected' : '' ?>>Indonesia (id)</option>
          <option value="en" <?= ($settings['language'] ?? 'id') === 'en' ? 'selected' : '' ?>>English (en)</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="s_wa">Nomor WhatsApp</label>
        <input type="text" id="s_wa" name="wa_number" class="form-control" value="<?= htmlspecialchars($settings['wa_number'] ?? '') ?>" placeholder="628xxxxxxxxxx">
      </div>
      <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px 14px;font-size:.83rem;line-height:1.6;color:var(--text-mid);margin-bottom:16px">
        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer">
          <input type="checkbox" name="whatsapp_shared_inbox_enabled" value="1"
                 <?= ($settings['whatsapp_shared_inbox_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
                 style="margin-top:3px">
          <span>
            <strong>Aktifkan WhatsApp Shared Inbox</strong><br>
            Jika aktif, nomor host WhatsApp cabang ini bisa menerima chat untuk semua cabang aktif dan customer akan diminta memilih cabang di awal chat.
          </span>
        </label>
      </div>
      <div style="padding:10px 14px;background:var(--coffee-cream);border-radius:var(--radius);font-size:.8rem;color:var(--text-mid);margin-bottom:16px">
        🔗 Link Order:
        <a href="<?= BASE_URL ?>/order.php?branch=<?= htmlspecialchars($branch['slug']) ?>" target="_blank" style="word-break:break-all">
          <?= BASE_URL ?>/order.php?branch=<?= htmlspecialchars($branch['slug']) ?>
        </a>
      </div>
      <div style="text-align:right">
        <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
      </div>
    </form>
  </div>

  <!-- Admins -->
  <div class="card">
    <div class="card-title" style="justify-content:space-between">
      👤 Admin Cabang
      <button class="btn btn-xs btn-primary" onclick="document.getElementById('addAdminModal').classList.remove('hidden')">+ Tambah</button>
    </div>
    <?php if (empty($admins)): ?>
      <p style="color:var(--text-light);font-size:.9rem">Belum ada admin untuk cabang ini.</p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nama</th>
            <th>Email</th>
            <th style="text-align:center">Status</th>
            <th style="text-align:center">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($admins as $admin): ?>
        <tr>
          <td><?= htmlspecialchars($admin['name']) ?></td>
          <td style="color:var(--text-mid);font-size:.85rem"><?= htmlspecialchars($admin['email']) ?></td>
          <td style="text-align:center">
            <span class="badge <?= $admin['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= $admin['is_active'] ? 'Aktif' : 'Nonaktif' ?></span>
          </td>
          <td style="text-align:center;white-space:nowrap">
            <form method="POST" style="display:inline">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="toggle_admin">
              <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
              <button class="btn btn-xs btn-outline" type="submit"><?= $admin['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
            </form>
            <button class="btn btn-xs btn-outline" style="margin-left:4px" onclick="openResetModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['name'], ENT_QUOTES) ?>')">Reset PW</button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Recent Orders -->
  <div class="card">
    <div class="card-title" style="justify-content:space-between">
      📦 Order Terbaru
      <a href="<?= BASE_URL ?>/dashboard/super/orders.php?branch=<?= $branchId ?>" class="btn btn-xs btn-outline">Lihat Semua</a>
    </div>
    <?php if (empty($recentOrders)): ?>
      <p style="color:var(--text-light);font-size:.9rem">Belum ada order.</p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>No. Order</th>
            <th>Customer</th>
            <th style="text-align:right">Total</th>
            <th style="text-align:center">Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($recentOrders as $o):
          $statusBadge = match($o['order_status'] ?? 'pending') {
            'completed'  => 'badge-green',
            'processing' => 'badge-blue',
            'cancelled'  => 'badge-red',
            default      => 'badge-gray',
          };
        ?>
        <tr>
          <td style="font-family:monospace;font-size:.85rem">
            <a href="<?= BASE_URL ?>/dashboard/super/order-detail.php?id=<?= $o['id'] ?>"><?= htmlspecialchars($o['order_number']) ?></a>
          </td>
          <td><?= htmlspecialchars($o['customer_name'] ?? '-') ?></td>
          <td style="text-align:right"><?= Currency::format((float)$o['total_amount'], $currency) ?></td>
          <td style="text-align:center">
            <span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($o['order_status'] ?? 'pending') ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- Add Admin Modal -->
<div id="addAdminModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Tambah Admin Cabang</div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="add_admin">
      <div class="form-group">
        <label class="form-label" for="admin_name">Nama *</label>
        <input type="text" id="admin_name" name="admin_name" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="admin_email">Email *</label>
        <input type="email" id="admin_email" name="admin_email" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="admin_password">Password *</label>
        <input type="password" id="admin_password" name="admin_password" class="form-control" minlength="6" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addAdminModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Tambah Admin</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPwModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Reset Password — <span id="resetAdminName"></span></div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="reset_user_id">
      <div class="form-group">
        <label class="form-label" for="new_password">Password Baru *</label>
        <input type="password" id="new_password" name="new_password" class="form-control" minlength="6" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('resetPwModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Reset Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function openResetModal(userId, name) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('resetAdminName').textContent = name;
    document.getElementById('resetPwModal').classList.remove('hidden');
}
</script>

<?php
$content = ob_get_clean();
echo View::renderLayout('Detail Cabang — ' . htmlspecialchars($branch['name']), $content, 'super_admin');
