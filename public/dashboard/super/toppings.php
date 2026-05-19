<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf, Sanitize};
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
        $name       = Sanitize::string($_POST['name'] ?? '');
        $slug       = Sanitize::slug($name);
        $priceDelta = (float)($_POST['price_delta'] ?? 0);
        $sortOrder  = (int)($_POST['sort_order']   ?? 0);

        if (!$name) {
            $error = 'Nama topping wajib diisi.';
        } else {
            try {
                $db->prepare(
                    'INSERT INTO menu_toppings (name, slug, price_delta, sort_order) VALUES (?,?,?,?)'
                )->execute([$name, $slug, $priceDelta, $sortOrder]);
                $message = "Topping '{$name}' berhasil ditambahkan.";
            } catch (\PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? "Slug '{$slug}' sudah ada." : 'Gagal menyimpan.';
            }
        }

    } elseif ($action === 'edit') {
        $id         = (int)$_POST['topping_id'];
        $name       = Sanitize::string($_POST['name'] ?? '');
        $slug       = Sanitize::slug($name);
        $priceDelta = (float)($_POST['price_delta'] ?? 0);
        $sortOrder  = (int)($_POST['sort_order']   ?? 0);

        if (!$id || !$name) {
            $error = 'Data tidak valid.';
        } else {
            try {
                $db->prepare(
                    'UPDATE menu_toppings SET name=?, slug=?, price_delta=?, sort_order=? WHERE id=?'
                )->execute([$name, $slug, $priceDelta, $sortOrder, $id]);
                $message = "Topping '{$name}' diperbarui.";
            } catch (\PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? "Slug '{$slug}' sudah dipakai." : 'Gagal menyimpan.';
            }
        }

    } elseif ($action === 'toggle') {
        $id = (int)$_POST['topping_id'];
        if ($id) {
            $db->prepare(
                'UPDATE menu_toppings SET is_active = IF(is_active=1,0,1) WHERE id=?'
            )->execute([$id]);
            $message = 'Status topping diubah.';
        }

    } elseif ($action === 'delete') {
        $id = (int)$_POST['topping_id'];
        if ($id) {
            $db->prepare('DELETE FROM menu_toppings WHERE id=?')->execute([$id]);
            $message = 'Topping dihapus.';
        }

    } elseif ($action === 'assign') {
        $toppingId   = (int)$_POST['topping_id'];
        $selectedIds = array_map('intval', (array)($_POST['menu_item_ids'] ?? []));

        if ($toppingId) {
            $db->prepare('DELETE FROM menu_item_toppings WHERE topping_id=?')->execute([$toppingId]);
            foreach ($selectedIds as $itemId) {
                if ($itemId) {
                    $db->prepare(
                        'INSERT IGNORE INTO menu_item_toppings (menu_item_id, topping_id) VALUES (?,?)'
                    )->execute([$itemId, $toppingId]);
                }
            }
            $message = 'Assignment topping disimpan.';
        }
    }
}

// Load toppings with usage count
$toppings = $db->query(
    "SELECT t.*,
            COUNT(mit.id) AS usage_count,
            GROUP_CONCAT(mi.name ORDER BY mi.name SEPARATOR ', ') AS linked_items
     FROM menu_toppings t
     LEFT JOIN menu_item_toppings mit ON mit.topping_id = t.id
     LEFT JOIN menu_items mi ON mi.id = mit.menu_item_id
     GROUP BY t.id
     ORDER BY t.sort_order, t.name"
)->fetchAll();

// Load all menu items for assign modal
$allMenuItems = $db->query(
    "SELECT mi.id, mi.name, mc.name AS category
     FROM menu_items mi
     JOIN menu_categories mc ON mi.category_id = mc.id
     WHERE mi.is_active = 1
     ORDER BY mc.sort_order, mi.name"
)->fetchAll();

// Index: topping_id => [menu_item_id, ...]
$assignmentsRaw = $db->query('SELECT topping_id, menu_item_id FROM menu_item_toppings')->fetchAll();
$assignments = [];
foreach ($assignmentsRaw as $a) {
    $assignments[(int)$a['topping_id']][] = (int)$a['menu_item_id'];
}

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="section-header">
  <h2>Manajemen Topping</h2>
  <div style="display:flex;gap:8px">
    <a href="<?= BASE_URL ?>/api/export/menu-toppings.php" class="btn btn-outline" style="font-size:.85rem">&#8595; Export CSV</a>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.remove('hidden')">+ Tambah Topping</button>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Nama</th>
          <th>Slug</th>
          <th>Harga Tambahan Global (IDR)</th>
          <th>Sort</th>
          <th>Dipakai di Menu</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($toppings)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--text-light);padding:32px">Belum ada topping</td></tr>
      <?php endif; ?>
      <?php foreach ($toppings as $t): ?>
      <tr>
        <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
        <td style="font-size:.8rem;color:var(--text-light)"><?= htmlspecialchars($t['slug']) ?></td>
        <td><?= $t['price_delta'] != 0 ? '+IDR ' . number_format((float)$t['price_delta'], 0, ',', '.') : '—' ?></td>
        <td><?= (int)$t['sort_order'] ?></td>
        <td style="font-size:.8rem;max-width:200px">
          <?php if ($t['usage_count'] > 0): ?>
            <span class="badge badge-blue"><?= (int)$t['usage_count'] ?> menu</span>
            <span style="color:var(--text-light)"> <?= htmlspecialchars($t['linked_items']) ?></span>
          <?php else: ?>
            <span style="color:var(--text-light)">—</span>
          <?php endif; ?>
        </td>
        <td>
          <span class="badge <?= $t['is_active'] ? 'badge-green' : 'badge-gray' ?>">
            <?= $t['is_active'] ? 'Aktif' : 'Nonaktif' ?>
          </span>
        </td>
        <td>
          <div style="display:flex;gap:4px;flex-wrap:wrap">
            <button class="btn btn-xs btn-primary" type="button"
              onclick="openEditModal(<?= $t['id'] ?>, <?= htmlspecialchars(json_encode($t['name']), ENT_QUOTES) ?>, <?= (float)$t['price_delta'] ?>, <?= (int)$t['sort_order'] ?>)">
              Edit
            </button>
            <button class="btn btn-xs btn-outline" type="button"
              onclick="openAssignModal(<?= $t['id'] ?>, <?= htmlspecialchars(json_encode($t['name']), ENT_QUOTES) ?>)">
              Assign Menu
            </button>
            <form method="POST" style="display:inline">
              <?= Csrf::field() ?>
              <input type="hidden" name="action"     value="toggle">
              <input type="hidden" name="topping_id" value="<?= $t['id'] ?>">
              <button class="btn btn-xs btn-outline" type="submit">
                <?= $t['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
              </button>
            </form>
            <form method="POST" style="display:inline"
                  onsubmit="return confirm('Hapus topping <?= htmlspecialchars($t['name'], ENT_QUOTES) ?>? Assignment ke semua menu akan ikut terhapus.')">
              <?= Csrf::field() ?>
              <input type="hidden" name="action"     value="delete">
              <input type="hidden" name="topping_id" value="<?= $t['id'] ?>">
              <button class="btn btn-xs btn-outline" style="color:var(--danger)" type="submit">Hapus</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Tambah Topping Baru</div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="add_name">Nama Topping *</label>
          <input type="text" name="name" id="add_name" class="form-control" placeholder="cth: Keju, Boba, Pudding" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="add_price">Harga Tambahan Global (IDR)</label>
          <input type="number" name="price_delta" id="add_price" class="form-control" min="0" step="500" value="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="add_sort">Sort Order</label>
        <input type="number" name="sort_order" id="add_sort" class="form-control" value="0" min="0">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Edit Topping</div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action"     value="edit">
      <input type="hidden" name="topping_id" id="edit_id">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="edit_name">Nama Topping *</label>
          <input type="text" name="name" id="edit_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_price">Harga Tambahan Global (IDR)</label>
          <input type="number" name="price_delta" id="edit_price" class="form-control" min="0" step="500">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="edit_sort">Sort Order</label>
        <input type="number" name="sort_order" id="edit_sort" class="form-control" min="0">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<!-- Assign Modal -->
<div id="assignModal" class="modal-overlay hidden">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-title">Assign Topping ke Menu — <span id="assign_topping_name"></span></div>
    <p style="font-size:.85rem;color:var(--text-mid);margin-bottom:12px">
      Centang menu item yang menggunakan topping ini.
    </p>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action"     value="assign">
      <input type="hidden" name="topping_id" id="assign_topping_id">
      <div id="assign_items_list" style="max-height:360px;overflow-y:auto;border:1px solid var(--border);border-radius:6px;padding:8px"></div>
      <div class="modal-footer" style="margin-top:14px">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('assignModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Assignment</button>
      </div>
    </form>
  </div>
</div>

<?php
$menuItemsJson  = json_encode($allMenuItems,  JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$assignmentsJson = json_encode($assignments,  JSON_NUMERIC_CHECK);
?>
<script>
const allMenuItems   = <?= $menuItemsJson ?>;
const assignments    = <?= $assignmentsJson ?>;

function openEditModal(id, name, price, sort) {
  document.getElementById('edit_id').value    = id;
  document.getElementById('edit_name').value  = name;
  document.getElementById('edit_price').value = price;
  document.getElementById('edit_sort').value  = sort;
  document.getElementById('editModal').classList.remove('hidden');
}

function openAssignModal(toppingId, toppingName) {
  document.getElementById('assign_topping_id').value       = toppingId;
  document.getElementById('assign_topping_name').textContent = toppingName;

  const assigned = assignments[toppingId] || [];
  const list     = document.getElementById('assign_items_list');

  // Group by category
  const byCategory = {};
  allMenuItems.forEach(item => {
    if (!byCategory[item.category]) byCategory[item.category] = [];
    byCategory[item.category].push(item);
  });

  let html = '';
  for (const [cat, items] of Object.entries(byCategory)) {
    html += `<div style="font-weight:600;font-size:.8rem;color:var(--text-mid);padding:6px 4px 2px;border-bottom:1px solid var(--border);margin-bottom:4px">${cat}</div>`;
    items.forEach(item => {
      const checked = assigned.includes(item.id) ? 'checked' : '';
      html += `<label style="display:flex;align-items:center;gap:8px;padding:5px 4px;cursor:pointer;border-radius:4px"
                      onmouseover="this.style.background='var(--bg-light)'" onmouseout="this.style.background=''">
        <input type="checkbox" name="menu_item_ids[]" value="${item.id}" ${checked}>
        <span>${item.name}</span>
      </label>`;
    });
  }
  if (!html) html = '<p style="color:var(--text-light);padding:8px">Tidak ada menu item aktif.</p>';

  list.innerHTML = html;
  document.getElementById('assignModal').classList.remove('hidden');
}
</script>

<?php
$content = ob_get_clean();
echo View::renderLayout('Manajemen Topping', $content, 'super_admin');


