<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;
use App\Helpers\{Auth, View, Currency, Csrf, Sanitize, MenuImage};
use App\Models\{MenuModel, BranchModel};
use App\Services\OpenAiMenuImageService;

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
$imageAi   = new OpenAiMenuImageService();
$categories = $menuModel->getCategories();

$findMenuItemWithCategory = static function (int $itemId) use ($db): ?array {
    $stmt = $db->prepare(
        'SELECT mi.*, mc.name AS category_name
         FROM menu_items mi
         LEFT JOIN menu_categories mc ON mc.id = mi.category_id
         WHERE mi.id = ?
         LIMIT 1'
    );
    $stmt->execute([$itemId]);
    $row = $stmt->fetch();
    return $row ?: null;
};

$resolveCategoryName = static function (int $categoryId) use ($categories): string {
    foreach ($categories as $category) {
        if ((int)($category['id'] ?? 0) === $categoryId) {
            return (string)($category['name'] ?? '');
        }
    }
    return '';
};

$createBranchMenuItem = static function (bool $generateAiImage) use ($menuModel, $imageAi, $db, $branchId, $resolveCategoryName): string {
    $catId = (int)($_POST['category_id'] ?? 0);
    $name  = Sanitize::string($_POST['name'] ?? '');
    $slug  = Sanitize::slug($name) . '-' . $branchId;
    $price = (float)($_POST['price'] ?? 0);
    $desc  = Sanitize::string($_POST['description'] ?? '');

    if (!$name || !$price) {
        throw new RuntimeException('Nama dan harga wajib diisi.');
    }

    $imagePath = MenuImage::uploadFromRequest('image', $slug ?: $name);
    if ($generateAiImage) {
        $generated = $imageAi->generateForMenu([
            'name' => $name,
            'description' => $desc,
            'category_name' => $resolveCategoryName($catId),
        ], [
            'prompt' => $_POST['ai_image_prompt'] ?? '',
            'style' => $_POST['ai_image_style'] ?? '',
            'size' => $_POST['ai_image_size'] ?? '1024x1024',
            'quality' => $_POST['ai_image_quality'] ?? 'medium',
        ], $branchId);

        if ($imagePath) {
            MenuImage::deleteManaged($imagePath);
        }
        $imagePath = $generated['relative_path'];
    }

    $itemId = $menuModel->insert([
        'category_id' => $catId,
        'name' => $name,
        'slug' => $slug,
        'description' => $desc,
        'price' => $price,
        'image_path' => $imagePath,
    ]);

    $db->prepare(
        'INSERT INTO branch_menu_overrides (branch_id, menu_item_id, custom_price, is_available)
         VALUES (?,?,?,1)'
    )->execute([$branchId, $itemId, $price]);

    return $name;
};

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
    } elseif ($action === 'add_item' || $action === 'add_item_generate_ai') {
        try {
            $name = $createBranchMenuItem($action === 'add_item_generate_ai');
            $message = $action === 'add_item_generate_ai'
                ? "Menu '{$name}' ditambahkan dengan foto AI."
                : "Menu '{$name}' ditambahkan.";
        } catch (Throwable $e) {
            $error = $e->getMessage();
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
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'generate_item_image') {
        $menuItemId = (int)($_POST['menu_item_id'] ?? 0);
        $menuItem = $findMenuItemWithCategory($menuItemId);

        if (!$menuItem) {
            $error = 'Menu tidak ditemukan.';
        } else {
            try {
                $generated = $imageAi->generateForMenu($menuItem, [
                    'prompt' => $_POST['ai_image_prompt'] ?? '',
                    'style' => $_POST['ai_image_style'] ?? '',
                    'size' => $_POST['ai_image_size'] ?? '1024x1024',
                    'quality' => $_POST['ai_image_quality'] ?? 'medium',
                ], $branchId);

                MenuImage::deleteManaged($menuItem['image_path'] ?? null);
                $menuModel->update($menuItemId, ['image_path' => $generated['relative_path']]);
                $message = "Foto AI untuk '{$menuItem['name']}' berhasil dibuat.";
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$branchModel = new BranchModel();
$currency    = $branchModel->getCurrency($branchId);
$priceStep   = in_array($currency, ['USD', 'SGD', 'AUD'], true) ? '0.01' : '500';

$grouped    = $menuModel->getMenuGrouped($branchId);
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
    <a href="<?= $exportBase ?>/menu-catalog-ai-branch.php" class="btn btn-outline" style="font-size:.85rem">&#8595; AI Export XLS</a>
    <button class="btn btn-outline" type="button" onclick="document.getElementById('uploadModal').classList.remove('hidden')">&#8593; AI Import Excel</button>
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
    <div class="modal-title">AI Import Katalog Menu</div>
    <p style="font-size:.85rem;color:var(--text-mid);margin-bottom:12px">
      Upload <code>.xlsx</code>, <code>.xls</code>, atau <code>.csv</code>. Sistem akan memakai LLM sesuai settings untuk mengenali sheet, header, dan tipe data, lalu melakukan <strong>update/insert</strong> ke tabel menu, variant, topping, dan availability.
    </p>
    <form method="POST" action="<?= BASE_URL ?>/api/upload/menu-ai.php" enctype="multipart/form-data" id="menuAiImportForm">
      <?= Csrf::field() ?>
      <input type="hidden" name="branch_id" value="<?= $branchId ?>">
      <input type="hidden" name="uploaded_file_id" id="menuAiUploadedFileId" value="">
      <input type="hidden" name="mapping_json" id="menuAiMappingJson" value="">
      <div class="form-group">
        <label class="form-label" for="uploadFile">File CSV/Excel</label>
        <input type="file" name="file" id="uploadFile" class="form-control" accept=".csv,.xlsx,.xls" required>
      </div>
      <div style="background:var(--bg-light,#faf9f7);border-radius:10px;padding:12px;margin-bottom:12px;font-size:.82rem;line-height:1.65">
        <strong>Data yang dikenali:</strong><br>
        menu item, kategori, harga global, harga cabang, variant/size, topping, relasi topping ke menu, availability cabang, status aktif/nonaktif.
      </div>
      <div id="menuAiImportResult" style="display:none;margin-bottom:12px;border-radius:10px;padding:12px;font-size:.83rem"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('uploadModal').classList.add('hidden')">Batal</button>
        <button type="button" class="btn btn-outline" id="menuAiPreviewSubmit">Preview AI</button>
        <button type="submit" class="btn btn-primary" id="menuAiImportSubmit" disabled>Konfirmasi Import</button>
      </div>
    </form>
  </div>
</div>

<div id="addModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Tambah Menu Baru</div>
    <form method="POST" enctype="multipart/form-data">
      <?= Csrf::field() ?>
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
      <div style="margin-bottom:14px;padding:12px;border:1px solid var(--border);border-radius:12px;background:#faf6f0">
        <div style="font-weight:700;color:var(--coffee-dark);margin-bottom:8px">Preset Style Foto AI</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="addAiPreset">Preset Kategori</label>
            <select id="addAiPreset" class="form-control">
              <option value="auto">Otomatis dari kategori</option>
              <option value="coffee">Coffee</option>
              <option value="bakery">Bakery</option>
              <option value="steak">Steak</option>
              <option value="dessert">Dessert</option>
              <option value="general">General Food</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="addAiImageSize">Ukuran</label>
            <select name="ai_image_size" id="addAiImageSize" class="form-control">
              <option value="1024x1024">Square 1024x1024</option>
              <option value="1536x1024">Landscape 1536x1024</option>
              <option value="1024x1536">Portrait 1024x1536</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="addAiImageStyle">Gaya Visual</label>
          <input type="text" name="ai_image_style" id="addAiImageStyle" class="form-control" value="foto produk studio realistis, premium, clean background, pencahayaan hangat">
        </div>
        <div class="form-group">
          <label class="form-label" for="addAiImagePrompt">Prompt Tambahan</label>
          <textarea name="ai_image_prompt" id="addAiImagePrompt" class="form-control" rows="2" placeholder="Contoh: tampilkan plating premium dengan sudut 3/4 dan fokus pada detail produk"></textarea>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label" for="addAiImageQuality">Kualitas</label>
          <select name="ai_image_quality" id="addAiImageQuality" class="form-control">
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="low">Low</option>
          </select>
          <small style="color:var(--text-mid);font-size:.82rem">Klik "Tambah + Generate AI" untuk langsung membuat item baru beserta foto produk OpenAI.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.add('hidden')">Batal</button>
        <button type="submit" name="action" value="add_item_generate_ai" class="btn btn-outline">Tambah + Generate AI</button>
        <button type="submit" name="action" value="add_item" class="btn btn-primary">Simpan</button>
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
      <div style="margin-bottom:14px;padding:12px;border:1px solid var(--border);border-radius:12px;background:#faf6f0">
        <div style="font-weight:700;color:var(--coffee-dark);margin-bottom:8px">Generate dengan OpenAI</div>
        <div class="form-group" style="margin-bottom:10px">
          <label class="form-label" for="img_ai_prompt">Prompt Tambahan</label>
          <textarea name="ai_image_prompt" id="img_ai_prompt" class="form-control" rows="2" placeholder="Contoh: tampilkan dari sudut 3/4 dengan background terang dan fokus pada detail produk"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="img_ai_style">Gaya Visual</label>
            <input type="text" name="ai_image_style" id="img_ai_style" class="form-control" value="foto produk studio realistis, premium, clean background, pencahayaan hangat">
          </div>
          <div class="form-group">
            <label class="form-label" for="img_ai_size">Ukuran</label>
            <select name="ai_image_size" id="img_ai_size" class="form-control">
              <option value="1024x1024">Square 1024x1024</option>
              <option value="1536x1024">Landscape 1536x1024</option>
              <option value="1024x1536">Portrait 1024x1536</option>
            </select>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label" for="img_ai_quality">Kualitas</label>
          <select name="ai_image_quality" id="img_ai_quality" class="form-control">
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="low">Low</option>
          </select>
        </div>
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:10px">
        <input type="checkbox" name="remove_image" id="img_remove_image" value="1">
        <label for="img_remove_image" class="form-label" style="margin:0">Hapus foto produk saat disimpan</label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('imageModal').classList.add('hidden')">Batal</button>
        <button type="submit" name="action" value="generate_item_image" class="btn btn-outline">Generate Foto AI</button>
        <button type="submit" name="action" value="update_item_image" class="btn btn-primary">Simpan Foto</button>
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
const aiImportForm = document.getElementById('menuAiImportForm');
const aiImportResult = document.getElementById('menuAiImportResult');
const aiImportSubmit = document.getElementById('menuAiImportSubmit');
const aiPreviewSubmit = document.getElementById('menuAiPreviewSubmit');
const aiUploadedFileId = document.getElementById('menuAiUploadedFileId');
const aiMappingJson = document.getElementById('menuAiMappingJson');
let menuAiPreviewData = null;
let menuAiEntityFieldOptions = {};

const menuImageStylePresets = {
  coffee: 'foto minuman kopi premium, gelas rapi, foam detail, lighting hangat, meja kayu, studio realistis, clean background',
  bakery: 'foto roti dan bakery premium, tekstur renyah lembut terlihat jelas, pencahayaan hangat, plating rapi, studio realistis',
  steak: 'foto steak atau daging premium, tekstur juicy dan grill mark jelas, plating elegan, dramatic warm lighting, studio realistis',
  dessert: 'foto dessert premium, manis elegan, tekstur creamy detail, plating cantik, soft lighting, studio realistis',
  general: 'foto produk makanan premium, menggugah selera, clean background, pencahayaan hangat, studio realistis'
};

function inferMenuPreset(categoryName) {
  const text = String(categoryName || '').toLowerCase();
  if (text.includes('kopi') || text.includes('coffee') || text.includes('latte') || text.includes('espresso')) return 'coffee';
  if (text.includes('bakery') || text.includes('roti') || text.includes('bread') || text.includes('pastry')) return 'bakery';
  if (text.includes('steak') || text.includes('daging') || text.includes('sapi') || text.includes('meat')) return 'steak';
  if (text.includes('dessert') || text.includes('cake') || text.includes('kue') || text.includes('sweet')) return 'dessert';
  return 'general';
}

function applyAddMenuAiPreset() {
  const categorySelect = document.getElementById('addCategoryId');
  const presetSelect = document.getElementById('addAiPreset');
  const styleInput = document.getElementById('addAiImageStyle');
  if (!categorySelect || !presetSelect || !styleInput) return;

  const categoryName = categorySelect.options[categorySelect.selectedIndex]?.text || '';
  const preset = presetSelect.value === 'auto' ? inferMenuPreset(categoryName) : presetSelect.value;
  styleInput.value = menuImageStylePresets[preset] || menuImageStylePresets.general;
}

function syncAddMenuAiPrompt() {
  const name = document.getElementById('addName')?.value?.trim() || '';
  const desc = document.getElementById('addDesc')?.value?.trim() || '';
  const promptInput = document.getElementById('addAiImagePrompt');
  if (!promptInput) return;

  promptInput.value = desc
    ? `Tampilkan ${name || 'menu ini'} dengan detail: ${desc}`
    : `Tampilkan ${name || 'menu ini'} sebagai foto produk yang menarik dan realistis.`;
}

document.getElementById('addCategoryId')?.addEventListener('change', applyAddMenuAiPreset);
document.getElementById('addAiPreset')?.addEventListener('change', applyAddMenuAiPreset);
document.getElementById('addName')?.addEventListener('input', syncAddMenuAiPrompt);
document.getElementById('addDesc')?.addEventListener('input', syncAddMenuAiPrompt);
applyAddMenuAiPreset();
syncAddMenuAiPrompt();

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
  document.getElementById('img_ai_prompt').value = `Tampilkan ${name} sebagai foto produk menu yang menarik dan realistis.`;
  document.getElementById('img_ai_style').value = 'foto produk studio realistis, premium, clean background, pencahayaan hangat';
  document.getElementById('img_ai_size').value = '1024x1024';
  document.getElementById('img_ai_quality').value = 'medium';

  const preview = document.getElementById('img_preview');
  preview.innerHTML = imageUrl
    ? `<img src="${imageUrl}" alt="Preview foto produk" style="width:96px;height:96px;object-fit:cover;border-radius:14px;border:1px solid var(--border)">`
    : `<div style="width:96px;height:96px;border-radius:14px;border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-light);background:#faf6f0">Belum ada foto</div>`;

  document.getElementById('imageModal').classList.remove('hidden');
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function formatFieldLabel(field) {
  return String(field || '').replace(/_/g, ' ');
}

function renderManualMappingEditor(data) {
  const sheets = Array.isArray(data.sheets) ? data.sheets : [];
  return sheets.map((sheet, sheetIndex) => {
    const entity = sheet.entity || 'ignore';
    const options = menuAiEntityFieldOptions[entity] || [];
    const headers = Array.isArray(sheet.headers) ? sheet.headers : [];
    const fieldMap = sheet.field_map || {};

    return `
      <div class="menu-ai-sheet" data-sheet-index="${sheetIndex}" style="margin-bottom:12px;padding:12px;border:1px solid rgba(0,0,0,.08);border-radius:10px;background:#fff">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
          <div>
            <strong>${escapeHtml(sheet.sheet_name)}</strong><br>
            <span style="color:#666">${escapeHtml(sheet.notes || '')}</span><br>
            <span style="font-size:.8rem;color:#666">Header: ${headers.map(escapeHtml).join(', ')}</span><br>
            <span style="font-size:.8rem;color:#666">Row: ${sheet.row_count || 0}</span>
          </div>
          <div style="min-width:220px">
            <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:4px">Entity Sheet</label>
            <select class="form-control menu-ai-entity-select" data-sheet-index="${sheetIndex}">
              ${Object.keys(menuAiEntityFieldOptions).map((entityKey) => `
                <option value="${escapeHtml(entityKey)}" ${entityKey === entity ? 'selected' : ''}>${escapeHtml(entityKey)}</option>
              `).join('')}
            </select>
          </div>
        </div>
        <div class="menu-ai-field-mappings" data-sheet-index="${sheetIndex}" style="margin-top:10px">
          ${renderFieldMappingControls(sheetIndex, entity, fieldMap, headers)}
        </div>
      </div>`;
  }).join('');
}

function renderFieldMappingControls(sheetIndex, entity, fieldMap, headers) {
  const fields = menuAiEntityFieldOptions[entity] || [];
  if (!fields.length) {
    return '<div style="font-size:.8rem;color:#666">Sheet ini akan diabaikan saat import.</div>';
  }

  return `<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px">`
    + fields.map((field) => {
      const selectedHeader = fieldMap[field] || '';
      return `
        <label style="display:block;font-size:.78rem">
          <span style="display:block;font-weight:600;margin-bottom:4px">${escapeHtml(formatFieldLabel(field))}</span>
          <select class="form-control menu-ai-field-select" data-sheet-index="${sheetIndex}" data-field="${escapeHtml(field)}">
            <option value="">-- kosong --</option>
            ${headers.map((header) => `
              <option value="${escapeHtml(header)}" ${header === selectedHeader ? 'selected' : ''}>${escapeHtml(header)}</option>
            `).join('')}
          </select>
        </label>`;
    }).join('')
    + `</div>`;
}

function buildManualMappingsFromPreview() {
  const sheets = Array.isArray(menuAiPreviewData?.sheets) ? menuAiPreviewData.sheets : [];
  return sheets.map((sheet, index) => {
    const entitySelect = document.querySelector(`.menu-ai-entity-select[data-sheet-index="${index}"]`);
    const entity = entitySelect ? entitySelect.value : (sheet.entity || 'ignore');
    const fieldMap = {};
    document.querySelectorAll(`.menu-ai-field-select[data-sheet-index="${index}"]`).forEach((select) => {
      if (select.value) {
        fieldMap[select.dataset.field] = select.value;
      }
    });
    return {
      sheet_name: sheet.sheet_name || `Sheet ${index + 1}`,
      entity,
      field_map: fieldMap,
      confidence: sheet.confidence || 1,
      notes: 'manual',
    };
  });
}

function bindManualMappingEditors() {
  document.querySelectorAll('.menu-ai-entity-select').forEach((select) => {
    select.addEventListener('change', function () {
      const index = this.dataset.sheetIndex;
      const container = document.querySelector(`.menu-ai-field-mappings[data-sheet-index="${index}"]`);
      const sheet = (menuAiPreviewData?.sheets || [])[Number(index)] || {};
      const headers = Array.isArray(sheet.headers) ? sheet.headers : [];
      const existing = buildManualMappingsFromPreview()[Number(index)] || {};
      container.innerHTML = renderFieldMappingControls(Number(index), this.value, existing.field_map || {}, headers);
    });
  });
}

async function runMenuAiPreview() {
  aiPreviewSubmit.disabled = true;
  aiImportSubmit.disabled = true;
  aiImportResult.style.display = 'block';
  aiImportResult.style.background = '#eef7fb';
  aiImportResult.style.color = '#0b4f6c';
  aiImportResult.innerHTML = 'LLM sedang mengenali struktur sheet dan menyiapkan preview mapping...';

  try {
    const previewForm = new FormData(aiImportForm);
    previewForm.delete('uploaded_file_id');
    const response = await fetch('<?= BASE_URL ?>/api/upload/menu-ai-preview.php', {
      method: 'POST',
      body: previewForm,
      credentials: 'same-origin',
    });
    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.message || 'Preview gagal');
    }

    const data = payload.data || {};
    menuAiPreviewData = data;
    menuAiEntityFieldOptions = data.entity_field_options || {};
    aiUploadedFileId.value = data.uploaded_file_id || '';
    aiMappingJson.value = '';
    aiImportSubmit.disabled = !aiUploadedFileId.value;
    aiImportResult.style.background = '#fffaf0';
    aiImportResult.style.color = '#6b4e16';
    aiImportResult.innerHTML = `
      <strong>${payload.message}</strong><br>
      Sheet: ${data.sheet_count || 0} | Total row data: ${data.total_rows || 0}<br>
      LLM: ${(data.llm_provider || 'none')} / ${(data.llm_model || '-')}<br><br>
      <div style="margin-bottom:10px;font-weight:600">Koreksi mapping manual bila perlu:</div>
      ${renderManualMappingEditor(data)}
      <div style="margin-top:8px"><strong>Kalau mapping sudah benar, klik "Konfirmasi Import".</strong></div>`;
    bindManualMappingEditors();
  } catch (error) {
    aiImportResult.style.background = '#fff1f0';
    aiImportResult.style.color = '#8a1c1c';
    aiImportResult.innerHTML = `<strong>Preview gagal.</strong><br>${error.message}`;
  } finally {
    aiPreviewSubmit.disabled = false;
  }
}

if (aiImportForm) {
  aiPreviewSubmit.addEventListener('click', runMenuAiPreview);

  aiImportForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (!aiUploadedFileId.value) {
      aiImportResult.style.display = 'block';
      aiImportResult.style.background = '#fff1f0';
      aiImportResult.style.color = '#8a1c1c';
      aiImportResult.innerHTML = 'Jalankan preview AI dulu sebelum konfirmasi import.';
      return;
    }
    aiImportSubmit.disabled = true;
    aiPreviewSubmit.disabled = true;
    aiImportSubmit.textContent = 'Mengimpor...';
    aiImportResult.style.display = 'block';
    aiImportResult.style.background = '#eef7fb';
    aiImportResult.style.color = '#0b4f6c';
    aiImportResult.innerHTML = 'Sistem sedang menjalankan upsert ke tabel menu berdasarkan preview AI...';

    try {
      const importForm = new FormData(aiImportForm);
      importForm.delete('file');
      const manualMappings = buildManualMappingsFromPreview();
      aiMappingJson.value = JSON.stringify(manualMappings);
      importForm.set('mapping_json', aiMappingJson.value);
      const response = await fetch(aiImportForm.action, {
        method: 'POST',
        body: importForm,
        credentials: 'same-origin',
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'Import gagal');
      }

      const data = payload.data || {};
      const mappings = Array.isArray(data.sheet_mappings) ? data.sheet_mappings : [];
      aiImportResult.style.background = '#edf9f0';
      aiImportResult.style.color = '#1b5e20';
      aiImportResult.innerHTML = `
        <strong>${payload.message}</strong><br>
        Item baru: ${data.inserted_items || 0} | Item update: ${data.updated_items || 0}<br>
        Variant baru: ${data.inserted_variants || 0} | Variant update: ${data.updated_variants || 0}<br>
        Topping baru: ${data.inserted_toppings || 0} | Topping update: ${data.updated_toppings || 0}<br>
        Link topping: ${data.linked_toppings || 0} | Availability update: ${data.updated_availability || 0}<br>
        Row dilewati: ${data.skipped_rows || 0}<br>
        LLM: ${(data.llm_provider || 'none')} / ${(data.llm_model || '-')}` + (mappings.length
          ? `<br><br><strong>Deteksi sheet:</strong><br>${mappings.map((m) => `${m.sheet_name || 'Sheet'} → ${m.entity || 'ignore'} (${m.notes || 'mapped'})`).join('<br>')}`
          : '');

      setTimeout(() => window.location.reload(), 1600);
    } catch (error) {
      aiImportResult.style.background = '#fff1f0';
      aiImportResult.style.color = '#8a1c1c';
      aiImportResult.innerHTML = `<strong>Import gagal.</strong><br>${error.message}`;
    } finally {
      aiImportSubmit.disabled = false;
      aiPreviewSubmit.disabled = false;
      aiImportSubmit.textContent = 'Konfirmasi Import';
    }
  });
}
</script>

<?php
$content = ob_get_clean();
echo View::renderLayout('Manajemen Menu', $content, 'branch_admin');
