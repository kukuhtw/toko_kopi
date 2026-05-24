<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;
use App\Helpers\{Auth, View, Currency, Csrf, Sanitize, MenuImage};
use App\Models\MenuModel;
use App\Services\OpenAiMenuImageService;

Auth::startSession();
Auth::requireRole('super_admin');

$db         = Database::getInstance();
$menuModel  = new MenuModel();
$imageAi    = new OpenAiMenuImageService();
$categories = $menuModel->getCategories();
$message    = '';
$error      = '';

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

$createMenuItem = static function (bool $generateAiImage) use ($menuModel, $imageAi, $resolveCategoryName): string {
    $name  = Sanitize::string($_POST['name'] ?? '');
    $slug  = Sanitize::slug($name);
    $price = (float)($_POST['price'] ?? 0);
    $catId = (int)($_POST['category_id'] ?? 0);
    $desc  = Sanitize::string($_POST['description'] ?? '');

    if (!$name || !$price || !$catId) {
        throw new RuntimeException('Nama, harga, dan kategori wajib diisi.');
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
        ]);

        if ($imagePath) {
            MenuImage::deleteManaged($imagePath);
        }
        $imagePath = $generated['relative_path'];
    }

    $menuModel->insert([
        'category_id' => $catId,
        'name' => $name,
        'slug' => $slug,
        'description' => $desc,
        'price' => $price,
        'image_path' => $imagePath,
    ]);

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

    if ($action === 'add_item' || $action === 'add_item_generate_ai') {
        try {
            $name = $createMenuItem($action === 'add_item_generate_ai');
            $message = $action === 'add_item_generate_ai'
                ? "Menu '{$name}' ditambahkan dengan foto AI."
                : "Menu '{$name}' ditambahkan.";
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'edit_item') {
        $id          = (int)($_POST['item_id'] ?? 0);
        $item        = $menuModel->find($id);
        $name        = Sanitize::string($_POST['name'] ?? '');
        $price       = (float)($_POST['price'] ?? 0);
        $catId       = (int)($_POST['category_id'] ?? 0);
        $desc        = Sanitize::string($_POST['description'] ?? '');
        $slug        = Sanitize::slug($name);
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;
        $removeImage = isset($_POST['remove_image']);

        if (!$item) {
            $error = 'Menu tidak ditemukan.';
        } elseif (!$name || !$price || !$catId) {
            $error = 'Nama, harga, dan kategori wajib diisi.';
        } else {
            try {
                $newImagePath = MenuImage::uploadFromRequest('image', $slug ?: $name);
                $imagePath = $item['image_path'] ?? null;

                if ($removeImage) {
                    MenuImage::deleteManaged($imagePath);
                    $imagePath = null;
                }

                if ($newImagePath) {
                    MenuImage::deleteManaged($imagePath);
                    $imagePath = $newImagePath;
                }

                $menuModel->update($id, [
                    'category_id'  => $catId,
                    'name'         => $name,
                    'slug'         => $slug,
                    'description'  => $desc,
                    'price'        => $price,
                    'image_path'   => $imagePath,
                    'is_available' => $isAvailable,
                ]);
                $message = "Menu '{$name}' diperbarui.";
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'generate_item_image') {
        $id = (int)($_POST['item_id'] ?? 0);
        $item = $findMenuItemWithCategory($id);

        if (!$item) {
            $error = 'Menu tidak ditemukan.';
        } else {
            try {
                $generated = $imageAi->generateForMenu($item, [
                    'prompt' => $_POST['ai_image_prompt'] ?? '',
                    'style' => $_POST['ai_image_style'] ?? '',
                    'size' => $_POST['ai_image_size'] ?? '1024x1024',
                    'quality' => $_POST['ai_image_quality'] ?? 'medium',
                ]);

                MenuImage::deleteManaged($item['image_path'] ?? null);
                $menuModel->update($id, ['image_path' => $generated['relative_path']]);
                $message = "Foto AI untuk '{$item['name']}' berhasil dibuat.";
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'add_variant') {
        $itemId     = (int)($_POST['menu_item_id'] ?? 0);
        $label      = Sanitize::string($_POST['variant_label'] ?? '');
        $slug       = Sanitize::slug($label);
        $priceDelta = (float)($_POST['price_delta'] ?? 0);
        if ($itemId && $label) {
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
    } elseif ($action === 'toggle_item') {
        $id   = (int)($_POST['item_id'] ?? 0);
        $item = $menuModel->find($id);
        if ($item) {
            $menuModel->update($id, ['is_active' => $item['is_active'] ? 0 : 1]);
            $message = 'Status menu diperbarui.';
        }
    }
}

$allItems = $menuModel->query(
    'SELECT mi.*, mc.name AS category_name FROM menu_items mi
     JOIN menu_categories mc ON mi.category_id = mc.id
     ORDER BY mc.sort_order, mi.sort_order, mi.name'
)->fetchAll();

$allVariantsRaw = $db->query(
    'SELECT * FROM menu_item_variants ORDER BY menu_item_id, sort_order, label'
)->fetchAll();
$variantsByItem = [];
foreach ($allVariantsRaw as $variant) {
    $variantsByItem[(int)$variant['menu_item_id']][] = $variant;
}

ob_start();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="section-header">
  <h2>Menu Global</h2>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/api/export/menu-items-super.php" class="btn btn-outline" style="font-size:.85rem">&#8595; Export Menu</a>
    <a href="<?= BASE_URL ?>/api/export/menu-variants-super.php" class="btn btn-outline" style="font-size:.85rem">&#8595; Export Variant</a>
    <a href="<?= BASE_URL ?>/api/export/menu-toppings.php" class="btn btn-outline" style="font-size:.85rem">&#8595; Export Topping</a>
    <button class="btn btn-primary" type="button" onclick="document.getElementById('addItemModal').classList.remove('hidden')">+ Tambah Menu</button>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Kategori</th><th>Produk</th><th>Harga</th><th>Variant/Size</th><th>Tersedia</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($allItems as $item): ?>
      <?php $imageUrl = MenuImage::publicUrl($item['image_path'] ?? null); ?>
      <tr>
        <td><span class="badge badge-blue"><?= htmlspecialchars($item['category_name']) ?></span></td>
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
        <td><?= Currency::format((float)$item['price']) ?></td>
        <td>
          <?php $variants = $variantsByItem[$item['id']] ?? []; ?>
          <?php if ($variants): ?>
            <?php foreach ($variants as $variant): ?>
              <span class="badge <?= $variant['is_active'] ? 'badge-blue' : 'badge-gray' ?>" style="margin-bottom:2px">
                <?= htmlspecialchars($variant['label']) ?>
                <?php if ((float)$variant['price_delta'] !== 0.0): ?>
                  <?= (float)$variant['price_delta'] > 0 ? '+' : '' ?><?= Currency::format((float)$variant['price_delta']) ?>
                <?php endif; ?>
              </span>
            <?php endforeach; ?>
          <?php else: ?>
            <span style="color:var(--text-light);font-size:.8rem">-</span>
          <?php endif; ?>
        </td>
        <td><span class="badge <?= $item['is_available'] ? 'badge-green' : 'badge-red' ?>"><?= $item['is_available'] ? 'Ya' : 'Tidak' ?></span></td>
        <td><span class="badge <?= $item['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= $item['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
        <td>
          <button
            class="btn btn-xs btn-primary"
            type="button"
            data-id="<?= $item['id'] ?>"
            data-category-id="<?= $item['category_id'] ?>"
            data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>"
            data-price="<?= htmlspecialchars((string)$item['price'], ENT_QUOTES) ?>"
            data-description="<?= htmlspecialchars((string)($item['description'] ?? ''), ENT_QUOTES) ?>"
            data-available="<?= (int)$item['is_available'] ?>"
            data-image-url="<?= htmlspecialchars((string)($imageUrl ?? ''), ENT_QUOTES) ?>"
            onclick="openEditModal(this)"
          >Edit</button>
          <button
            class="btn btn-xs btn-outline"
            type="button"
            onclick="openVariantModal(<?= $item['id'] ?>, <?= htmlspecialchars(json_encode($item['name']), ENT_QUOTES) ?>)"
          >Variant</button>
          <form method="POST" style="display:inline">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="toggle_item">
            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
            <button class="btn btn-xs btn-outline" type="submit"><?= $item['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="addItemModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Tambah Menu Global</div>
    <form method="POST" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <div class="form-group">
        <label class="form-label" for="add_category_id">Kategori *</label>
        <select name="category_id" id="add_category_id" class="form-control" required>
          <?php foreach ($categories as $category): ?>
          <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="add_name">Nama *</label>
          <input type="text" name="name" id="add_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="add_price">Harga Global (IDR) *</label>
          <input type="number" name="price" id="add_price" class="form-control" min="0" step="500" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="add_desc">Deskripsi</label>
        <textarea name="description" id="add_desc" class="form-control" rows="2"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label" for="add_image">Foto Produk</label>
        <input type="file" name="image" id="add_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
        <small style="color:var(--text-mid);font-size:.82rem">Format JPG, PNG, WEBP, atau GIF. Maksimal 5 MB.</small>
      </div>
      <div style="margin-bottom:14px;padding:12px;border:1px solid var(--border);border-radius:12px;background:#faf6f0">
        <div style="font-weight:700;color:var(--coffee-dark);margin-bottom:8px">Preset Style Foto AI</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="add_ai_preset">Preset Kategori</label>
            <select id="add_ai_preset" class="form-control">
              <option value="auto">Otomatis dari kategori</option>
              <option value="coffee">Coffee</option>
              <option value="bakery">Bakery</option>
              <option value="steak">Steak</option>
              <option value="dessert">Dessert</option>
              <option value="general">General Food</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="add_ai_image_size">Ukuran</label>
            <select name="ai_image_size" id="add_ai_image_size" class="form-control">
              <option value="1024x1024">Square 1024x1024</option>
              <option value="1536x1024">Landscape 1536x1024</option>
              <option value="1024x1536">Portrait 1024x1536</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="add_ai_image_style">Gaya Visual</label>
          <input type="text" name="ai_image_style" id="add_ai_image_style" class="form-control" value="foto produk studio realistis, premium, clean background, pencahayaan hangat">
        </div>
        <div class="form-group">
          <label class="form-label" for="add_ai_image_prompt">Prompt Tambahan</label>
          <textarea name="ai_image_prompt" id="add_ai_image_prompt" class="form-control" rows="2" placeholder="Contoh: tampilkan plating premium dengan bayangan lembut dan fokus pada detail tekstur"></textarea>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label" for="add_ai_image_quality">Kualitas</label>
          <select name="ai_image_quality" id="add_ai_image_quality" class="form-control">
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="low">Low</option>
          </select>
          <small style="color:var(--text-mid);font-size:.82rem">Klik "Tambah + Generate AI" untuk langsung membuat item baru beserta foto produk OpenAI.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addItemModal').classList.add('hidden')">Batal</button>
        <button type="submit" name="action" value="add_item_generate_ai" class="btn btn-outline">Tambah + Generate AI</button>
        <button type="submit" name="action" value="add_item" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<div id="editItemModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title">Edit Menu Global</div>
    <form method="POST" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <input type="hidden" name="item_id" id="edit_item_id">
      <div class="form-group">
        <label class="form-label" for="edit_category_id">Kategori *</label>
        <select name="category_id" id="edit_category_id" class="form-control" required>
          <?php foreach ($categories as $category): ?>
          <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="edit_name">Nama *</label>
          <input type="text" name="name" id="edit_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_price">Harga Global (IDR) *</label>
          <input type="number" name="price" id="edit_price" class="form-control" min="0" step="500" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="edit_description">Deskripsi</label>
        <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Foto Produk</label>
        <div id="edit_image_preview" style="margin-bottom:10px"></div>
        <input type="file" name="image" id="edit_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
        <div style="display:flex;align-items:center;gap:10px;margin-top:8px">
          <input type="checkbox" name="remove_image" id="edit_remove_image" value="1">
          <label for="edit_remove_image" class="form-label" style="margin:0">Hapus foto produk saat disimpan</label>
        </div>
        <div style="margin-top:12px;padding:12px;border:1px solid var(--border);border-radius:12px;background:#faf6f0">
          <div style="font-weight:700;color:var(--coffee-dark);margin-bottom:8px">Generate dengan OpenAI</div>
          <div class="form-group" style="margin-bottom:10px">
            <label class="form-label" for="edit_ai_image_prompt">Prompt Tambahan</label>
            <textarea name="ai_image_prompt" id="edit_ai_image_prompt" class="form-control" rows="2" placeholder="Contoh: tampilkan plating premium dengan background terang dan fokus pada tekstur produk"></textarea>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="edit_ai_image_style">Gaya Visual</label>
              <input type="text" name="ai_image_style" id="edit_ai_image_style" class="form-control" value="foto produk studio realistis, premium, clean background, pencahayaan hangat">
            </div>
            <div class="form-group">
              <label class="form-label" for="edit_ai_image_size">Ukuran</label>
              <select name="ai_image_size" id="edit_ai_image_size" class="form-control">
                <option value="1024x1024">Square 1024x1024</option>
                <option value="1536x1024">Landscape 1536x1024</option>
                <option value="1024x1536">Portrait 1024x1536</option>
              </select>
            </div>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label" for="edit_ai_image_quality">Kualitas</label>
            <select name="ai_image_quality" id="edit_ai_image_quality" class="form-control">
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="low">Low</option>
            </select>
            <small style="color:var(--text-mid);font-size:.82rem">Gunakan setelah nama dan deskripsi menu sudah rapi supaya hasil foto lebih relevan.</small>
          </div>
        </div>
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:10px">
        <input type="checkbox" name="is_available" id="edit_is_available" value="1">
        <label for="edit_is_available" class="form-label" style="margin:0">Tersedia untuk dijual</label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('editItemModal').classList.add('hidden')">Batal</button>
        <button type="submit" name="action" value="generate_item_image" class="btn btn-outline">Generate Foto AI</button>
        <button type="submit" name="action" value="edit_item" class="btn btn-primary">Simpan Perubahan</button>
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
          <label class="form-label" for="newVarDelta">Selisih Harga Global (IDR)</label>
          <input type="number" name="price_delta" id="newVarDelta" class="form-control" step="500" placeholder="0 (sama), +5000, -2000">
          <small style="color:var(--text-mid);font-size:.8rem">Ditambahkan ke harga base. 0 = sama dengan base.</small>
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
          <label class="form-label" for="editVarDelta">Selisih Harga Global (IDR)</label>
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
$variantsJson = json_encode($variantsByItem, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_TAG);
$csrfName     = htmlspecialchars(CSRF_TOKEN_NAME, ENT_QUOTES);
$csrfValue    = htmlspecialchars(Csrf::generate(), ENT_QUOTES);
?>
<script>
const allVariants = <?= $variantsJson ?>;
const csrfName = '<?= $csrfName ?>';
const csrfValue = '<?= $csrfValue ?>';

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
  const categorySelect = document.getElementById('add_category_id');
  const presetSelect = document.getElementById('add_ai_preset');
  const styleInput = document.getElementById('add_ai_image_style');
  if (!categorySelect || !presetSelect || !styleInput) return;

  const categoryName = categorySelect.options[categorySelect.selectedIndex]?.text || '';
  const preset = presetSelect.value === 'auto' ? inferMenuPreset(categoryName) : presetSelect.value;
  styleInput.value = menuImageStylePresets[preset] || menuImageStylePresets.general;
}

function syncAddMenuAiPrompt() {
  const name = document.getElementById('add_name')?.value?.trim() || '';
  const desc = document.getElementById('add_desc')?.value?.trim() || '';
  const promptInput = document.getElementById('add_ai_image_prompt');
  if (!promptInput) return;

  promptInput.value = desc
    ? `Tampilkan ${name || 'menu ini'} dengan detail: ${desc}`
    : `Tampilkan ${name || 'menu ini'} sebagai foto produk yang menarik dan realistis.`;
}

document.getElementById('add_category_id')?.addEventListener('change', applyAddMenuAiPreset);
document.getElementById('add_ai_preset')?.addEventListener('change', applyAddMenuAiPreset);
document.getElementById('add_name')?.addEventListener('input', syncAddMenuAiPrompt);
document.getElementById('add_desc')?.addEventListener('input', syncAddMenuAiPrompt);
applyAddMenuAiPreset();
syncAddMenuAiPrompt();

function formatDelta(delta) {
  delta = parseFloat(delta) || 0;
  if (delta === 0) return '';
  return (delta > 0 ? '+' : '-') + 'IDR ' + Math.abs(delta).toLocaleString('id-ID');
}

function openVariantModal(itemId, itemName) {
  document.getElementById('variantItemName').textContent = itemName;
  document.getElementById('variantItemId').value = itemId;
  document.getElementById('newVarLabel').value = '';
  document.getElementById('newVarDelta').value = '';

  const variants = allVariants[itemId] || [];
  const list = document.getElementById('variantList');

  if (variants.length === 0) {
    list.innerHTML = '<p style="color:var(--text-light);font-size:.85rem">Belum ada variant.</p>';
  } else {
    list.innerHTML = `
      <table style="width:100%;border-collapse:collapse;font-size:.88rem">
        <thead>
          <tr style="border-bottom:1px solid var(--border)">
            <th style="padding:6px 8px;text-align:left">Label</th>
            <th style="padding:6px 8px;text-align:left">Selisih Harga</th>
            <th style="padding:6px 8px;text-align:left">Status</th>
            <th style="padding:6px 8px"></th>
          </tr>
        </thead>
        <tbody>
          ${variants.map(v => `
            <tr style="border-bottom:1px solid var(--border)">
              <td style="padding:6px 8px"><strong>${v.label}</strong></td>
              <td style="padding:6px 8px">${formatDelta(v.price_delta) || '-'}</td>
              <td style="padding:6px 8px">
                <span style="font-size:.8rem;color:${v.is_active=='1'?'var(--success)':'var(--text-light)'}">
                  ${v.is_active=='1' ? 'Aktif' : 'Nonaktif'}
                </span>
              </td>
              <td style="padding:6px 8px;display:flex;gap:4px">
                <button class="btn btn-xs btn-primary" type="button"
                        onclick="openEditVariant(${v.id},'${v.label.replace(/'/g,"\\'")}',${v.price_delta})">Edit</button>
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Hapus variant ' + v.label + '?')">
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
              </td>
            </tr>
          `).join('')}
        </tbody>
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

function openEditModal(button) {
  const itemName = button.dataset.name || '';
  const itemDescription = button.dataset.description || '';
  document.getElementById('edit_item_id').value = button.dataset.id || '';
  document.getElementById('edit_category_id').value = button.dataset.categoryId || '';
  document.getElementById('edit_name').value = itemName;
  document.getElementById('edit_price').value = button.dataset.price || '';
  document.getElementById('edit_description').value = itemDescription;
  document.getElementById('edit_is_available').checked = button.dataset.available === '1';
  document.getElementById('edit_remove_image').checked = false;
  document.getElementById('edit_image').value = '';
  document.getElementById('edit_ai_image_prompt').value = itemDescription
    ? `Tampilkan ${itemName} dengan detail: ${itemDescription}`
    : `Tampilkan ${itemName} sebagai foto produk menu yang menarik dan realistis.`;
  document.getElementById('edit_ai_image_style').value = 'foto produk studio realistis, premium, clean background, pencahayaan hangat';
  document.getElementById('edit_ai_image_size').value = '1024x1024';
  document.getElementById('edit_ai_image_quality').value = 'medium';

  const imageUrl = button.dataset.imageUrl || '';
  const preview = document.getElementById('edit_image_preview');
  preview.innerHTML = imageUrl
    ? `<img src="${imageUrl}" alt="Preview foto produk" style="width:88px;height:88px;object-fit:cover;border-radius:14px;border:1px solid var(--border)">`
    : `<div style="width:88px;height:88px;border-radius:14px;border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-light);background:#faf6f0">Belum ada foto</div>`;

  document.getElementById('editItemModal').classList.remove('hidden');
}
</script>

<?php
$content = ob_get_clean();
echo View::renderLayout('Menu Global', $content, 'super_admin');
