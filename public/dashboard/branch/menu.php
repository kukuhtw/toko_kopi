<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;
use App\Helpers\{Auth, View, Currency, Csrf, Sanitize, MenuImage};
use App\Models\{MenuModel, BranchModel};

Auth::startSession();
Auth::requireLogin();

$user     = Auth::user();
$branchId = (int)$user['branch_id'];
if (!$branchId && Auth::isSuperAdmin()) {
    $branchId = (int)($_GET['branch_id'] ?? 0);
}
if (!$branchId) {
    exit('Branch not found');
}

$message = '';
$error   = '';

$db        = Database::getInstance();
$menuModel = new MenuModel();

$renderMenuThumb = static function (?string $imagePath, string $name): string {
    $imageUrl = MenuImage::publicUrl($imagePath);
    if ($imageUrl) {
        return '<img src="' . htmlspecialchars($imageUrl, ENT_QUOTES) . '" alt="' . htmlspecialchars($name, ENT_QUOTES) . '" style="width:54px;height:54px;object-fit:cover;border-radius:12px;border:1px solid var(--border);background:#fff">';
    }

    return '<div style="width:54px;height:54px;border-radius:12px;border:1px dashed var(--border);background:linear-gradient(135deg,#f4e4d0,#fff8f0);display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--coffee-brown)">IMG</div>';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_variant') {
        $itemId     = (int)($_POST['menu_item_id'] ?? 0);
        $label      = Sanitize::string($_POST['variant_label'] ?? '');
        $priceDelta = (float)($_POST['price_delta'] ?? 0);
        if ($itemId && $label) {
            $slug = Sanitize::slug($label);
            $db->prepare(
                'INSERT INTO menu_item_variants (menu_item_id, label, slug, price_delta)
                 VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE label=VALUES(label), price_delta=VALUES(price_delta)'
            )->execute([$itemId, $label, $slug, $priceDelta]);
            $message = "Variant '{$label}' ditambahkan.";
        }
    } elseif ($action === 'edit_variant') {
        $varId      = (int)($_POST['variant_id'] ?? 0);
        $label      = Sanitize::string($_POST['variant_label'] ?? '');
        $priceDelta = (float)($_POST['price_delta'] ?? 0);
        if ($varId && $label) {
            $db->prepare(
                'UPDATE menu_item_variants SET label=?, slug=?, price_delta=? WHERE id=?'
            )->execute([$label, Sanitize::slug($label), $priceDelta, $varId]);
            $message = "Variant '{$label}' diperbarui.";
        }
    } elseif ($action === 'delete_variant') {
        $varId = (int)($_POST['variant_id'] ?? 0);
        if ($varId) {
            $db->prepare('DELETE FROM menu_item_variants WHERE id=?')->execute([$varId]);
            $message = 'Variant dihapus.';
        }
    } elseif ($action === 'toggle_variant') {
        $varId = (int)($_POST['variant_id'] ?? 0);
        if ($varId) {
            $db->prepare(
                'UPDATE menu_item_variants SET is_active=IF(is_active=1,0,1) WHERE id=?'
            )->execute([$varId]);
            $message = 'Status variant diubah.';
        }
    } elseif ($action === 'save_variant_override') {
        $varId      = (int)($_POST['variant_id'] ?? 0);
        $priceDelta = $_POST['price_delta'] === '' ? null : (float)$_POST['price_delta'];
        if ($varId) {
            if ($priceDelta === null) {
                $db->prepare(
                    'DELETE FROM branch_menu_variant_overrides WHERE branch_id=? AND variant_id=?'
                )->execute([$branchId, $varId]);
                $message = 'Override variant dihapus, kembali ke harga global.';
            } else {
                $db->prepare(
                    'INSERT INTO branch_menu_variant_overrides (branch_id, variant_id, price_delta)
                     VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE price_delta=VALUES(price_delta), is_active=1'
                )->execute([$branchId, $varId, $priceDelta]);
                $message = 'Override harga variant disimpan.';
            }
        }
    } elseif ($action === 'override_price') {
        $menuItemId  = (int)($_POST['menu_item_id'] ?? 0);
        $customPrice = (float)($_POST['custom_price'] ?? 0);
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;

        $db->prepare(
            'INSERT INTO branch_menu_overrides (branch_id, menu_item_id, custom_price, is_available)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE custom_price=VALUES(custom_price), is_available=VALUES(is_available)'
        )->execute([$branchId, $menuItemId, $customPrice > 0 ? $customPrice : null, $isAvailable]);
        $message = 'Harga dan ketersediaan diperbarui.';
    } elseif ($action === 'add_item') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $name  = Sanitize::string($_POST['name'] ?? '');
        $slug  = Sanitize::slug($name) . '-' . $branchId;
        $price = (float)($_POST['price'] ?? 0);
        $desc  = Sanitize::string($_POST['description'] ?? '');

        if (!$name || !$price) {
            $error = 'Nama dan harga wajib diisi.';
        } else {
            try {
                $imagePath = MenuImage::uploadFromRequest('image', $slug ?: $name);
                $itemId = $menuModel->insert([
                    'category_id' => $catId,
                    'name'        => $name,
                    'slug'        => $slug,
                    'description' => $desc,
                    'price'       => $price,
                    'image_path'  => $imagePath,
                ]);
                $db->prepare(
                    'INSERT INTO branch_menu_overrides (branch_id, menu_item_id, custom_price, is_available)
                     VALUES (?,?,?,1)'
                )->execute([$branchId, $itemId, $price]);
                $message = "Menu '{$name}' ditambahkan.";
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'update_item_image') {
        $menuItemId   = (int)($_POST['menu_item_id'] ?? 0);
        $removeImage  = isset($_POST['remove_image']);
        $menuItem     = $menuModel->find($menuItemId);

        if (!$menuItem) {
            $error = 'Menu tidak ditemukan.';
        } else {
            try {
                $newImagePath = MenuImage::uploadFromRequest('image', (string)($menuItem['slug'] ?? $menuItem['name']));
                $imagePath = $menuItem['image_path'] ?? null;

                if ($removeImage) {
                    MenuImage::deleteManaged($imagePath);
                    $imagePath = null;
                }

                if ($newImagePath) {
                    MenuImage::deleteManaged($imagePath);
                    $imagePath = $newImagePath;
                }

                if (!$removeImage && !$newImagePath) {
                    $error = 'Pilih foto baru atau centang hapus foto.';
                } else {
                    $menuModel->update($menuItemId, ['image_path' => $imagePath]);
                    $message = 'Foto produk diperbarui.';
                }
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$branchModel = new BranchModel();
$currency    = $branchModel->getCurrency($branchId);
$priceStep   = in_array($currency, ['USD', 'SGD', 'AUD'], true) ? '0.01' : '500';

$grouped    = $menuModel->getMenuGrouped($branchId);
$categories = $menuModel->getCategories();

$allVariantsRaw = $db->query(
    'SELECT * FROM menu_item_variants ORDER BY menu_item_id, sort_order, label'
)->fetchAll();
$variantsByItem = [];
foreach ($allVariantsRaw as $variant) {
    $variantsByItem[(int)$variant['menu_item_id']][] = $variant;
}

$overridesRaw = $db->prepare(
    'SELECT * FROM branch_menu_variant_overrides WHERE branch_id=? AND is_active=1'
);
$overridesRaw->execute([$branchId]);
$branchVariantOverrides = [];
foreach ($overridesRaw->fetchAll() as $override) {
    $branchVariantOverrides[(int)$override['variant_id']] = (float)$override['price_delta'];
}

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php
$exportBase = BASE_URL . '/api/export';
?>
<div class="section-header">
  <h2>Manajemen Menu</h2>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="<?= $exportBase ?>/menu-items-branch.php" class="btn btn-outline" style="font-size:.85rem">&#8595; Export Menu</a>
    <a href="<?= $exportBase ?>/menu-variants-branch.php" class="btn btn-outline" style="font-size:.85rem">&#8595; Export Variant</a>
    <a href="<?= $exportBase ?>/menu-toppings.php" class="btn btn-outline" style="font-size:.85rem">&#8595; Export Topping</a>
    <button class="btn btn-outline" type="button" onclick="document.getElementById('uploadModal').classList.remove('hidden')">&#8593; Upload CSV</button>
    <button class="btn btn-primary" type="button" onclick="document.getElementById('addModal').classList.remove('hidden')">+ Tambah Item</button>
  </div>
</div>

<?php foreach ($grouped as $category => $items): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-title"><?= htmlspecialchars($category) ?></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Produk</th><th>Harga Global (IDR)</th><th>Harga Cabang (<?= htmlspecialchars($currency) ?>)</th><th>Variant/Size</th><th>Tersedia</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($items as $item): ?>
      <?php $imageUrl = MenuImage::publicUrl($item['image_path'] ?? null); ?>
      <tr>
        <td>
          <div style="display:flex;align-items:flex-start;gap:12px;min-width:250px">
            <?= $renderMenuThumb($item['image_path'] ?? null, (string)$item['name']) ?>
            <div>
              <strong><?= htmlspecialchars($item['name']) ?></strong>
              <?php if (!empty($item['description'])): ?><br><small style="color:var(--text-light)"><?= htmlspecialchars(substr((string)$item['description'], 0, 60)) ?>...</small><?php endif; ?>
              <?php if ($imageUrl): ?><br><small style="color:var(--text-light)">Foto produk aktif</small><?php endif; ?>
            </div>
          </div>
        </td>
        <td><?= Currency::format((float)$item['price'], 'IDR') ?></td>
        <td>
          <?php if ($item['effective_price'] != $item['price']): ?>
          <span style="color:var(--coffee-brown);font-weight:600"><?= Currency::format((float)$item['effective_price'], $currency) ?></span>
          <?php else: ?><span style="color:var(--text-light)">-</span><?php endif; ?>
        </td>
        <td>
          <?php $variants = $variantsByItem[$item['id']] ?? []; ?>
          <?php if ($variants): ?>
            <?php foreach ($variants as $variant): ?>
              <?php
                $hasOverride = isset($branchVariantOverrides[$variant['id']]);
                $needsOverride = !$hasOverride && $currency !== 'IDR';
                $effectiveDelta = $hasOverride ? $branchVariantOverrides[$variant['id']] : (float)$variant['price_delta'];
                $effectiveCur = $hasOverride ? $currency : 'IDR';
                if (!$variant['is_active']) {
                    $badgeClass = 'badge-gray';
                    $badgeTitle = 'Variant nonaktif';
                } elseif ($hasOverride) {
                    $badgeClass = 'badge-green';
                    $badgeTitle = 'Override cabang ' . $currency;
                } elseif ($needsOverride) {
                    $badgeClass = 'badge-gray';
                    $badgeTitle = 'Belum ada harga ' . $currency . ' - klik Variant untuk set';
                } else {
                    $badgeClass = 'badge-blue';
                    $badgeTitle = 'Harga global IDR';
                }
              ?>
              <span class="badge <?= $badgeClass ?>" style="margin-bottom:2px" title="<?= htmlspecialchars($badgeTitle) ?>">
                <?= htmlspecialchars($variant['label']) ?>
                <?php if ($needsOverride): ?>
                  <span style="font-size:.7rem">!</span>
                <?php elseif ($effectiveDelta != 0): ?>
                  <?= $effectiveDelta > 0 ? '+' : '' ?><?= Currency::format($effectiveDelta, $effectiveCur) ?>
                <?php endif; ?>
              </span>
            <?php endforeach; ?>
          <?php else: ?>
            <span style="color:var(--text-light);font-size:.8rem">-</span>
          <?php endif; ?>
        </td>
        <td>
          <span class="badge <?= (bool)$item['effective_available'] ? 'badge-green' : 'badge-red' ?>">
            <?= (bool)$item['effective_available'] ? 'Ya' : 'Tidak' ?>
          </span>
        </td>
        <td style="display:flex;gap:4px;flex-wrap:wrap">
          <button class="btn btn-xs btn-outline" type="button"
            onclick="openOverrideModal(<?= $item['id'] ?>,'<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>',<?= (float)$item['effective_price'] ?>,<?= (int)(bool)$item['effective_available'] ?>)">
            Edit Harga
          </button>
          <button class="btn btn-xs btn-outline" type="button"
            onclick="openImageModal(<?= $item['id'] ?>, <?= htmlspecialchars(json_encode($item['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($imageUrl ?? ''), ENT_QUOTES) ?>)">
            Foto
          </button>
          <button class="btn btn-xs btn-outline" type="button"
            onclick="openVariantModal(<?= $item['id'] ?>, <?= htmlspecialchars(json_encode($item['name']), ENT_QUOTES) ?>)">
            Variant
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<div id="uploadModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Upload Menu (CSV)</div>
    <p style="font-size:.85rem;color:var(--text-mid);margin-bottom:12px">Format: <code>category_name, name, description, price</code></p>
    <form method="POST" action="<?= BASE_URL ?>/api/upload/menu.php" enctype="multipart/form-data">
      <input type="hidden" name="branch_id" value="<?= $branchId ?>">
      <div class="form-group">
        <label class="form-label" for="uploadFile">File CSV/Excel</label>
        <input type="file" name="file" id="uploadFile" class="form-control" accept=".csv,.xlsx,.xls" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('uploadModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Upload</button>
      </div>
    </form>
  </div>
</div>

<div id="addModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Tambah Menu Baru</div>
    <form method="POST" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="add_item">
      <div class="form-group">
        <label class="form-label" for="addCategoryId">Kategori *</label>
        <select name="category_id" id="addCategoryId" class="form-control" required>
          <?php foreach ($categories as $category): ?>
          <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="addName">Nama Menu *</label>
          <input type="text" name="name" id="addName" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="addPrice">Harga *</label>
          <input type="number" name="price" id="addPrice" class="form-control" min="0" step="<?= $priceStep ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="addDesc">Deskripsi</label>
        <textarea name="description" id="addDesc" class="form-control" rows="2"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label" for="addImage">Foto Produk</label>
        <input type="file" name="image" id="addImage" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
        <small style="color:var(--text-mid);font-size:.82rem">Format JPG, PNG, WEBP, atau GIF. Maksimal 5 MB.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<div id="overrideModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Edit Harga / Ketersediaan</div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="override_price">
      <input type="hidden" name="menu_item_id" id="ov_item_id">
      <div class="form-group">
        <p class="form-label" style="margin:0">Item: <strong id="ov_item_name"></strong></p>
      </div>
      <div class="form-group">
        <label class="form-label" for="ov_price">Harga Cabang (kosongkan = ikut global)</label>
        <input type="number" name="custom_price" id="ov_price" class="form-control" min="0" step="<?= $priceStep ?>">
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:10px">
        <input type="checkbox" name="is_available" id="ov_available" value="1" checked>
        <label for="ov_available" class="form-label" style="margin:0">Tersedia di cabang ini</label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('overrideModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<div id="imageModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Kelola Foto Produk</div>
    <form method="POST" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="update_item_image">
      <input type="hidden" name="menu_item_id" id="img_item_id">
      <div class="form-group">
        <p class="form-label" style="margin:0">Item: <strong id="img_item_name"></strong></p>
      </div>
      <div class="form-group">
        <label class="form-label">Preview</label>
        <div id="img_preview"></div>
      </div>
      <div class="form-group">
        <label class="form-label" for="img_file">Upload Foto Baru</label>
        <input type="file" name="image" id="img_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
        <small style="color:var(--text-mid);font-size:.82rem">Format JPG, PNG, WEBP, atau GIF. Maksimal 5 MB.</small>
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:10px">
        <input type="checkbox" name="remove_image" id="img_remove_image" value="1">
        <label for="img_remove_image" class="form-label" style="margin:0">Hapus foto produk saat disimpan</label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('imageModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Foto</button>
      </div>
    </form>
  </div>
</div>

<div id="variantModal" class="modal-overlay hidden">
  <div class="modal-box" style="max-width:600px">
    <div class="modal-title">Variant / Size - <span id="variantItemName"></span></div>
    <div id="variantList" style="margin-bottom:18px"></div>
    <form method="POST" id="addVariantForm">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="add_variant">
      <input type="hidden" name="menu_item_id" id="variantItemId">
      <p style="font-weight:600;margin-bottom:8px">Tambah Variant Baru</p>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="newVarLabel">Label *</label>
          <input type="text" name="variant_label" id="newVarLabel" class="form-control" placeholder="Small / Medium / Large" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="newVarDelta">Selisih Harga <span style="color:var(--text-light)">(IDR)</span></label>
          <input type="number" name="price_delta" id="newVarDelta" class="form-control" step="500" placeholder="0">
          <small style="color:var(--text-mid);font-size:.8rem">Variant disimpan global dalam IDR. Contoh: 3000 = +IDR 3.000.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('variantModal').classList.add('hidden')">Tutup</button>
        <button type="submit" class="btn btn-primary">Tambah Variant</button>
      </div>
    </form>
  </div>
</div>

<div id="editVariantModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Edit Variant</div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="edit_variant">
      <input type="hidden" name="variant_id" id="editVarId">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="editVarLabel">Label *</label>
          <input type="text" name="variant_label" id="editVarLabel" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="editVarDelta">Selisih Harga <span style="color:var(--text-light)">(IDR)</span></label>
          <input type="number" name="price_delta" id="editVarDelta" class="form-control" step="500">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('editVariantModal').classList.add('hidden')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php
$variantsJson  = json_encode($variantsByItem, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_TAG);
$overridesJson = json_encode($branchVariantOverrides, JSON_NUMERIC_CHECK);
$csrfName      = htmlspecialchars(CSRF_TOKEN_NAME, ENT_QUOTES);
$csrfValue     = htmlspecialchars(Csrf::generate(), ENT_QUOTES);
?>
<script>
const allVariants = <?= $variantsJson ?>;
const branchOverrides = <?= $overridesJson ?>;
const csrfName = '<?= $csrfName ?>';
const csrfValue = '<?= $csrfValue ?>';
const branchCurrency = '<?= htmlspecialchars($currency) ?>';

function fmtGlobalDelta(delta) {
  if (delta === 0) return '-';
  return (delta > 0 ? '+' : '') + 'IDR ' + Math.abs(delta).toLocaleString('id-ID');
}

function fmtOverrideDelta(val) {
  const abs = Math.abs(val);
  const sign = val >= 0 ? '+' : '-';
  if (branchCurrency === 'IDR') return sign + 'IDR ' + abs.toLocaleString('id-ID');
  return sign + branchCurrency + ' ' + abs.toFixed(2);
}

function openVariantModal(itemId, itemName) {
  document.getElementById('variantItemName').textContent = itemName;
  document.getElementById('variantItemId').value = itemId;
  document.getElementById('newVarLabel').value = '';
  document.getElementById('newVarDelta').value = '';

  const variants = allVariants[itemId] || [];
  const list = document.getElementById('variantList');
  const overrideStep = branchCurrency === 'IDR' ? '500' : '0.01';

  if (!variants.length) {
    list.innerHTML = '<p style="color:var(--text-light);font-size:.85rem">Belum ada variant.</p>';
  } else {
    list.innerHTML = `
      <table style="width:100%;border-collapse:collapse;font-size:.83rem">
        <thead><tr style="border-bottom:1px solid var(--border)">
          <th style="padding:5px 6px;text-align:left">Label</th>
          <th style="padding:5px 6px;text-align:left">Global (IDR)</th>
          <th style="padding:5px 6px;text-align:left">Override Cabang (${branchCurrency})</th>
          <th style="padding:5px 6px;text-align:left">Status</th>
          <th style="padding:5px 6px"></th>
        </tr></thead>
        <tbody>${variants.map(v => {
          const delta = parseFloat(v.price_delta) || 0;
          const deltaStr = fmtGlobalDelta(delta);
          const overrideVal = branchOverrides[v.id];
          const hasOverride = overrideVal !== undefined;
          const overrideDisplay = hasOverride
            ? `<span style="color:var(--success);font-weight:600">${fmtOverrideDelta(overrideVal)}</span>`
            : `<span style="color:var(--text-light)">-</span>`;
          const safeLabel = v.label.replace(/'/g,"\\'").replace(/"/g,'&quot;');
          return `<tr style="border-bottom:1px solid var(--border)">
            <td style="padding:5px 6px"><strong>${v.label}</strong></td>
            <td style="padding:5px 6px">${deltaStr}</td>
            <td style="padding:5px 6px">
              <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                ${overrideDisplay}
                <form method="POST" style="display:inline-flex;gap:4px;align-items:center">
                  <input type="hidden" name="${csrfName}" value="${csrfValue}">
                  <input type="hidden" name="action" value="save_variant_override">
                  <input type="hidden" name="variant_id" value="${v.id}">
                  <input type="number" name="price_delta"
                         value="${hasOverride ? overrideVal : ''}"
                         step="${overrideStep}"
                         style="width:72px;padding:3px 5px;border:1px solid var(--border);border-radius:4px;font-size:.8rem"
                         placeholder="${hasOverride ? overrideVal : '0'}">
                  <button class="btn btn-xs btn-primary" type="submit">Simpan</button>
                </form>
                ${hasOverride ? `<form method="POST" style="display:inline">
                  <input type="hidden" name="${csrfName}" value="${csrfValue}">
                  <input type="hidden" name="action" value="save_variant_override">
                  <input type="hidden" name="variant_id" value="${v.id}">
                  <input type="hidden" name="price_delta" value="">
                  <button class="btn btn-xs btn-outline" style="color:var(--danger)" type="submit"
                          onclick="return confirm('Reset ke harga global IDR?')">Reset</button>
                </form>` : ''}
              </div>
            </td>
            <td style="padding:5px 6px;font-size:.8rem;color:${v.is_active=='1'?'var(--success)':'var(--text-light)'}">
              ${v.is_active=='1' ? 'Aktif' : 'Nonaktif'}</td>
            <td style="padding:5px 6px">
              <div style="display:flex;gap:4px;flex-wrap:wrap">
                <button class="btn btn-xs btn-primary" type="button"
                  onclick="openEditVariant(${v.id},'${safeLabel}',${v.price_delta})">Edit</button>
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Hapus variant ${safeLabel}?')">
                  <input type="hidden" name="${csrfName}" value="${csrfValue}">
                  <input type="hidden" name="action" value="delete_variant">
                  <input type="hidden" name="variant_id" value="${v.id}">
                  <button class="btn btn-xs btn-outline" style="color:var(--danger)">Hapus</button>
                </form>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="${csrfName}" value="${csrfValue}">
                  <input type="hidden" name="action" value="toggle_variant">
                  <input type="hidden" name="variant_id" value="${v.id}">
                  <button class="btn btn-xs btn-outline">${v.is_active=='1'?'Nonaktifkan':'Aktifkan'}</button>
                </form>
              </div>
            </td>
          </tr>`;
        }).join('')}</tbody>
      </table>`;
  }

  document.getElementById('variantModal').classList.remove('hidden');
}

function openEditVariant(id, label, delta) {
  document.getElementById('editVarId').value = id;
  document.getElementById('editVarLabel').value = label;
  document.getElementById('editVarDelta').value = delta;
  document.getElementById('editVariantModal').classList.remove('hidden');
}

function openOverrideModal(id, name, price, available) {
  document.getElementById('ov_item_id').value = id;
  document.getElementById('ov_item_name').textContent = name;
  document.getElementById('ov_price').value = price || '';
  document.getElementById('ov_available').checked = !!available;
  document.getElementById('overrideModal').classList.remove('hidden');
}

function openImageModal(id, name, imageUrl) {
  document.getElementById('img_item_id').value = id;
  document.getElementById('img_item_name').textContent = name;
  document.getElementById('img_remove_image').checked = false;
  document.getElementById('img_file').value = '';

  const preview = document.getElementById('img_preview');
  preview.innerHTML = imageUrl
    ? `<img src="${imageUrl}" alt="Preview foto produk" style="width:96px;height:96px;object-fit:cover;border-radius:14px;border:1px solid var(--border)">`
    : `<div style="width:96px;height:96px;border-radius:14px;border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-light);background:#faf6f0">Belum ada foto</div>`;

  document.getElementById('imageModal').classList.remove('hidden');
}
</script>

<?php
$content = ob_get_clean();
echo View::renderLayout('Manajemen Menu', $content, 'branch_admin');
