<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Config/config.php';
require_once __DIR__ . '/NewsCmsRepository.php';
require_once __DIR__ . '/OpenAiArticleGenerator.php';
require_once __DIR__ . '/OpenAiImageGenerator.php';

use App\Helpers\{Auth, View, Csrf};

Auth::startSession();
Auth::requireLogin();

$user = Auth::user();
$role = ($user['role'] ?? '') === 'super_admin' ? 'super_admin' : 'branch_admin';
$pageTitle = $role === 'super_admin' ? 'CMS Berita Toko' : 'CMS Berita Cabang';
$currentPagePath = $role === 'super_admin'
    ? '/dashboard/super/berita.php'
    : '/dashboard/branch/berita.php';
$repo = new NewsCmsRepository();
$generator = new OpenAiArticleGenerator();
$imageGenerator = new OpenAiImageGenerator();
$message = '';
$error = '';

$rawStatusFilter = (string)($_GET['status'] ?? 'all');
$statusFilter = in_array($rawStatusFilter, ['all', 'draft', 'published'], true)
    ? $rawStatusFilter
    : 'all';
$search = trim((string)($_GET['q'] ?? ''));
$editId = isset($_GET['id']) ? max(0, (int)$_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = (string)($_POST['action'] ?? '');
    $articleId = isset($_POST['article_id']) && $_POST['article_id'] !== ''
        ? max(0, (int)$_POST['article_id'])
        : null;

    if ($action === 'save_article') {
        $result = $repo->saveForAdmin(
            $_POST,
            (int)($user['id'] ?? 0),
            $role,
            (int)($user['branch_id'] ?? 0),
            $articleId
        );

        if ($result['ok']) {
            $message = (string)$result['message'];
            $editId = (int)($result['id'] ?? 0);
        } else {
            $error = (string)$result['message'];
        }
    } elseif ($action === 'generate_article') {
        try {
            $generated = $generator->generate([
                'topic' => $_POST['ai_topic'] ?? '',
                'angle' => $_POST['ai_angle'] ?? '',
                'audience' => $_POST['ai_audience'] ?? '',
                'tone' => $_POST['ai_tone'] ?? '',
                'keywords' => $_POST['ai_keywords'] ?? '',
                'cta' => $_POST['ai_cta'] ?? '',
                'notes' => $_POST['ai_notes'] ?? '',
                'desired_length' => $_POST['ai_desired_length'] ?? 'medium',
                'branch_id' => $_POST['branch_id'] ?? ($user['branch_id'] ?? 0),
            ]);
            $_POST['title'] = $generated['title'];
            $_POST['excerpt'] = $generated['excerpt'];
            $_POST['content'] = $generated['content'];
            $message = 'Draft artikel berhasil digenerate dengan OpenAI. Silakan review lalu simpan.';
        } catch (\Throwable $e) {
            $error = 'Generate artikel gagal: ' . $e->getMessage();
        }
    } elseif ($action === 'generate_cover_image') {
        try {
            $generatedImage = $imageGenerator->generate([
                'topic' => $_POST['ai_topic'] ?? ($_POST['title'] ?? ''),
                'prompt' => $_POST['ai_image_prompt'] ?? '',
                'style' => $_POST['ai_image_style'] ?? '',
                'size' => $_POST['ai_image_size'] ?? '1536x1024',
                'quality' => $_POST['ai_image_quality'] ?? 'medium',
                'branch_id' => $_POST['branch_id'] ?? ($user['branch_id'] ?? 0),
            ]);
            $_POST['cover_image'] = $generatedImage['public_url'];
            $message = 'Cover image berhasil digenerate dengan OpenAI dan dimasukkan ke field cover.';
        } catch (\Throwable $e) {
            $error = 'Generate gambar gagal: ' . $e->getMessage();
        }
    } elseif ($action === 'delete_article' && $articleId !== null) {
        if ($repo->deleteForAdmin($articleId, $role, (int)($user['branch_id'] ?? 0))) {
            $message = 'Artikel berita berhasil dihapus.';
            $editId = 0;
        } else {
            $error = 'Artikel tidak ditemukan atau tidak bisa dihapus.';
        }
    }
}

$editing = $editId > 0 ? $repo->findForAdmin($editId, $role, (int)($user['branch_id'] ?? 0)) : false;
if ($editId > 0 && !$editing && $error === '') {
    $error = 'Artikel yang diminta tidak ditemukan.';
}

$articles = $repo->getArticlesForAdmin($role, (int)($user['branch_id'] ?? 0), $statusFilter, $search);
$branches = $role === 'super_admin' ? $repo->getBranches() : [];

$form = [
    'id'           => $editing['id'] ?? '',
    'branch_id'    => (string)($editing['branch_id'] ?? ($role === 'super_admin' ? '0' : (string)($user['branch_id'] ?? 0))),
    'title'        => (string)($editing['title'] ?? ''),
    'excerpt'      => (string)($editing['excerpt'] ?? ''),
    'content'      => (string)($editing['content'] ?? ''),
    'cover_image'  => (string)($editing['cover_image'] ?? ''),
    'status'       => (string)($editing['status'] ?? 'draft'),
    'is_featured'  => !empty($editing['is_featured']),
    'published_at' => isset($editing['published_at']) && $editing['published_at']
        ? date('Y-m-d\TH:i', strtotime((string)$editing['published_at']))
        : '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string)($_POST['action'] ?? ''), ['save_article', 'generate_article', 'generate_cover_image'], true)) {
    $form['branch_id'] = (string)($_POST['branch_id'] ?? $form['branch_id']);
    $form['title'] = (string)($_POST['title'] ?? $form['title']);
    $form['excerpt'] = (string)($_POST['excerpt'] ?? $form['excerpt']);
    $form['content'] = (string)($_POST['content'] ?? $form['content']);
    $form['cover_image'] = (string)($_POST['cover_image'] ?? $form['cover_image']);
    $form['status'] = in_array(($_POST['status'] ?? $form['status']), ['draft', 'published'], true)
        ? (string)$_POST['status']
        : $form['status'];
    $form['is_featured'] = !empty($_POST['is_featured']);
    $form['published_at'] = (string)($_POST['published_at'] ?? $form['published_at']);
}

$rawDesiredLength = (string)($_POST['ai_desired_length'] ?? 'medium');
$rawImageSize = (string)($_POST['ai_image_size'] ?? '1536x1024');
$rawImageQuality = (string)($_POST['ai_image_quality'] ?? 'medium');

$generatorForm = [
    'topic' => (string)($_POST['ai_topic'] ?? ''),
    'angle' => (string)($_POST['ai_angle'] ?? ''),
    'audience' => (string)($_POST['ai_audience'] ?? ''),
    'tone' => (string)($_POST['ai_tone'] ?? 'hangat dan profesional'),
    'keywords' => (string)($_POST['ai_keywords'] ?? ''),
    'cta' => (string)($_POST['ai_cta'] ?? ''),
    'notes' => (string)($_POST['ai_notes'] ?? ''),
    'desired_length' => in_array($rawDesiredLength, ['short', 'medium', 'long'], true)
        ? $rawDesiredLength
        : 'medium',
    'image_prompt' => (string)($_POST['ai_image_prompt'] ?? ''),
    'image_style' => (string)($_POST['ai_image_style'] ?? 'foto editorial coffee shop realistis'),
    'image_size' => in_array($rawImageSize, ['1024x1024', '1536x1024', '1024x1536'], true)
        ? $rawImageSize
        : '1536x1024',
    'image_quality' => in_array($rawImageQuality, ['low', 'medium', 'high'], true)
        ? $rawImageQuality
        : 'medium',
];

$publicUrl = BASE_URL . '/berita.php';
$branchScopeLabel = $role === 'branch_admin'
    ? htmlspecialchars((string)($user['name'] ?? 'Admin Cabang'))
    : '';

ob_start();
?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <div style="display:flex;flex-wrap:wrap;gap:14px;justify-content:space-between;align-items:flex-start">
    <div>
      <div class="card-title">Berita Toko</div>
      <p style="margin:6px 0 0;color:var(--text-mid);line-height:1.6;max-width:760px">
        Kelola artikel berita, kabar promo, update event, atau pengumuman toko dari dashboard.
        Artikel <strong>published</strong> akan tampil di halaman publik <a href="<?= htmlspecialchars($publicUrl) ?>" target="_blank"><?= htmlspecialchars($publicUrl) ?></a>.
      </p>
    </div>
    <a href="<?= htmlspecialchars(BASE_URL . $currentPagePath) ?>" class="btn btn-outline btn-sm">Artikel Baru</a>
  </div>
</div>

<div style="display:grid;grid-template-columns:minmax(320px,1.15fr) minmax(360px,.95fr);gap:16px;align-items:start">
  <div class="card" style="margin:0">
    <div class="card-title"><?= $editing ? 'Edit Artikel' : 'Buat Artikel Baru' ?></div>
    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="article_id" value="<?= htmlspecialchars((string)$form['id']) ?>">

      <?php if ($role === 'super_admin'): ?>
      <div class="form-group">
        <label class="form-label" for="news_branch_id">Target Berita</label>
        <select id="news_branch_id" name="branch_id" class="form-control">
          <option value="0" <?= $form['branch_id'] === '0' ? 'selected' : '' ?>>Global / Semua Cabang</option>
          <?php foreach ($branches as $branch): ?>
          <option value="<?= (int)$branch['id'] ?>" <?= $form['branch_id'] === (string)$branch['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars((string)$branch['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
      <div style="background:linear-gradient(135deg,#f5e7d8,#fff8f1);border:1px solid var(--border);border-radius:14px;padding:14px 16px;margin-bottom:16px">
        <div style="font-weight:700;color:var(--coffee-dark);margin-bottom:4px">Konten khusus cabang Anda</div>
        <p style="margin:0;color:var(--text-mid);line-height:1.6">
          Artikel yang dibuat dari akun branch admin hanya akan tersimpan untuk cabang Anda.
          Anda tidak perlu memilih cabang manual, dan artikel ini tidak bisa dipindahkan ke cabang lain dari halaman ini.
        </p>
      </div>
      <?php endif; ?>

      <div style="background:var(--bg-light);border-radius:14px;padding:16px;margin-bottom:16px">
        <div style="font-weight:700;color:var(--coffee-dark);margin-bottom:4px">Generate dengan OpenAI</div>
        <p style="margin:0 0 14px;color:var(--text-mid);line-height:1.6">
          Isi brief singkat, generate draft artikel, lalu review hasilnya sebelum disimpan.
          Konfigurasi memakai <code>OPENAI_API_KEY</code> / <code>OPENAI_MODEL</code> dari <code>.env</code>, atau setting global jika provider aplikasi diset ke OpenAI.
        </p>

        <div class="form-group">
          <label class="form-label" for="ai_topic">Topik Artikel</label>
          <input type="text" id="ai_topic" name="ai_topic" class="form-control"
                 value="<?= htmlspecialchars($generatorForm['topic']) ?>" placeholder="Contoh: launching menu kopi aren terbaru">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="ai_angle">Sudut Bahasan</label>
            <input type="text" id="ai_angle" name="ai_angle" class="form-control"
                   value="<?= htmlspecialchars($generatorForm['angle']) ?>" placeholder="Apa yang ingin ditonjolkan?">
          </div>
          <div class="form-group">
            <label class="form-label" for="ai_audience">Target Audiens</label>
            <input type="text" id="ai_audience" name="ai_audience" class="form-control"
                   value="<?= htmlspecialchars($generatorForm['audience']) ?>" placeholder="Pelanggan kampus, pekerja kantor, keluarga...">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="ai_tone">Tone</label>
            <input type="text" id="ai_tone" name="ai_tone" class="form-control"
                   value="<?= htmlspecialchars($generatorForm['tone']) ?>" placeholder="Hangat, premium, santai, persuasif">
          </div>
          <div class="form-group">
            <label class="form-label" for="ai_desired_length">Panjang Artikel</label>
            <select id="ai_desired_length" name="ai_desired_length" class="form-control">
              <option value="short" <?= $generatorForm['desired_length'] === 'short' ? 'selected' : '' ?>>Pendek</option>
              <option value="medium" <?= $generatorForm['desired_length'] === 'medium' ? 'selected' : '' ?>>Sedang</option>
              <option value="long" <?= $generatorForm['desired_length'] === 'long' ? 'selected' : '' ?>>Panjang</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="ai_keywords">Kata Kunci Penting</label>
          <input type="text" id="ai_keywords" name="ai_keywords" class="form-control"
                 value="<?= htmlspecialchars($generatorForm['keywords']) ?>" placeholder="kopi susu, seasonal menu, promo buy 1 get 1">
        </div>

        <div class="form-group">
          <label class="form-label" for="ai_cta">Call to Action</label>
          <input type="text" id="ai_cta" name="ai_cta" class="form-control"
                 value="<?= htmlspecialchars($generatorForm['cta']) ?>" placeholder="Ajak pembaca mampir, coba menu, atau follow akun">
        </div>

        <div class="form-group" style="margin-bottom:0">
          <label class="form-label" for="ai_notes">Catatan Tambahan</label>
          <textarea id="ai_notes" name="ai_notes" class="form-control" rows="3"
                    placeholder="Detail promo, jadwal event, pembeda brand, atau batasan gaya bahasa"><?= htmlspecialchars($generatorForm['notes']) ?></textarea>
        </div>
      </div>

      <div style="background:var(--bg-light);border-radius:14px;padding:16px;margin-bottom:16px">
        <div style="font-weight:700;color:var(--coffee-dark);margin-bottom:4px">Generate Cover Image</div>
        <p style="margin:0 0 14px;color:var(--text-mid);line-height:1.6">
          Buat gambar cover artikel dengan OpenAI. File hasil generate akan disimpan ke folder <code>uploads/berita</code>.
        </p>

        <div class="form-group">
          <label class="form-label" for="ai_image_prompt">Prompt Gambar</label>
          <textarea id="ai_image_prompt" name="ai_image_prompt" class="form-control" rows="3"
                    placeholder="Contoh: secangkir kopi aren di meja kayu dengan suasana coffee shop modern dan hangat"><?= htmlspecialchars($generatorForm['image_prompt']) ?></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="ai_image_style">Gaya Visual</label>
            <input type="text" id="ai_image_style" name="ai_image_style" class="form-control"
                   value="<?= htmlspecialchars($generatorForm['image_style']) ?>" placeholder="realistis, editorial, premium, soft lighting">
          </div>
          <div class="form-group">
            <label class="form-label" for="ai_image_size">Ukuran</label>
            <select id="ai_image_size" name="ai_image_size" class="form-control">
              <option value="1536x1024" <?= $generatorForm['image_size'] === '1536x1024' ? 'selected' : '' ?>>Landscape 1536x1024</option>
              <option value="1024x1024" <?= $generatorForm['image_size'] === '1024x1024' ? 'selected' : '' ?>>Square 1024x1024</option>
              <option value="1024x1536" <?= $generatorForm['image_size'] === '1024x1536' ? 'selected' : '' ?>>Portrait 1024x1536</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="ai_image_quality">Kualitas</label>
            <select id="ai_image_quality" name="ai_image_quality" class="form-control">
              <option value="low" <?= $generatorForm['image_quality'] === 'low' ? 'selected' : '' ?>>Low</option>
              <option value="medium" <?= $generatorForm['image_quality'] === 'medium' ? 'selected' : '' ?>>Medium</option>
              <option value="high" <?= $generatorForm['image_quality'] === 'high' ? 'selected' : '' ?>>High</option>
            </select>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="news_title">Judul Berita</label>
        <input type="text" id="news_title" name="title" class="form-control"
               value="<?= htmlspecialchars($form['title']) ?>" placeholder="Contoh: Grand opening menu seasonal baru" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="news_excerpt">Ringkasan Singkat</label>
        <textarea id="news_excerpt" name="excerpt" class="form-control" rows="3"
                  placeholder="Satu paragraf singkat untuk teaser berita"><?= htmlspecialchars($form['excerpt']) ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="news_content">Isi Berita</label>
        <textarea id="news_content" name="content" class="form-control" rows="12"
                  placeholder="Tulis isi berita toko di sini..." required><?= htmlspecialchars($form['content']) ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="news_cover">URL Gambar Cover</label>
        <input type="url" id="news_cover" name="cover_image" class="form-control"
               value="<?= htmlspecialchars($form['cover_image']) ?>" placeholder="https://...">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="news_status">Status</label>
          <select id="news_status" name="status" class="form-control">
            <option value="draft" <?= $form['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= $form['status'] === 'published' ? 'selected' : '' ?>>Published</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="news_published_at">Waktu Publish</label>
          <input type="datetime-local" id="news_published_at" name="published_at" class="form-control"
                 value="<?= htmlspecialchars($form['published_at']) ?>">
        </div>
      </div>

      <label style="display:flex;gap:10px;align-items:flex-start;background:var(--bg-light);padding:12px 14px;border-radius:10px;margin:0 0 16px">
        <input type="checkbox" name="is_featured" value="1" <?= $form['is_featured'] ? 'checked' : '' ?> style="margin-top:3px">
        <span>
          <strong>Tandai sebagai berita unggulan</strong><br>
          <small style="color:var(--text-mid)">Berita unggulan akan tampil lebih atas di halaman publik.</small>
        </span>
      </label>

      <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <button type="submit" name="action" value="generate_article" class="btn btn-outline">Generate dengan OpenAI</button>
        <button type="submit" name="action" value="generate_cover_image" class="btn btn-outline">Generate Cover Image</button>
        <button type="submit" name="action" value="save_article" class="btn btn-primary"><?= $editing ? 'Simpan Perubahan' : 'Buat Artikel' ?></button>
        <?php if ($editing): ?>
        <a href="<?= htmlspecialchars(BASE_URL . $currentPagePath) ?>" class="btn btn-outline">Reset Form</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div>
    <div class="card" style="margin:0 0 16px">
      <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end">
        <?php if (!empty($editId)): ?>
        <input type="hidden" name="id" value="<?= (int)$editId ?>">
        <?php endif; ?>
        <div class="form-group" style="margin:0;min-width:140px">
          <label class="form-label" for="news_status_filter">Filter Status</label>
          <select id="news_status_filter" name="status" class="form-control">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Semua</option>
            <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Published</option>
          </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:180px">
          <label class="form-label" for="news_q">Cari Artikel</label>
          <input type="search" id="news_q" name="q" class="form-control"
                 value="<?= htmlspecialchars($search) ?>" placeholder="Judul, ringkasan, isi...">
        </div>
        <button type="submit" class="btn btn-outline">Terapkan</button>
      </form>
    </div>

    <div class="card" style="margin:0">
      <div class="card-title">Daftar Artikel</div>
      <?php if (empty($articles)): ?>
      <p style="color:var(--text-light);margin:0">Belum ada artikel yang cocok dengan filter saat ini.</p>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:12px">
        <?php foreach ($articles as $article): ?>
        <?php
          $isEditing = (int)$article['id'] === (int)$editId;
          $articleUrl = $publicUrl . '?slug=' . urlencode((string)$article['slug']);
          $scopeLabel = !empty($article['branch_name']) ? $article['branch_name'] : 'Global';
        ?>
        <div style="border:1px solid var(--border);border-radius:12px;padding:14px;background:<?= $isEditing ? 'var(--bg-light)' : '#fff' ?>">
          <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
            <div>
              <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:6px">
                <span class="badge <?= $article['status'] === 'published' ? 'badge-green' : 'badge-gray' ?>">
                  <?= htmlspecialchars(ucfirst((string)$article['status'])) ?>
                </span>
                <span class="badge badge-gray"><?= htmlspecialchars($scopeLabel) ?></span>
                <?php if (!empty($article['is_featured'])): ?>
                <span class="badge badge-gray">Unggulan</span>
                <?php endif; ?>
              </div>
              <div style="font-weight:700;color:var(--coffee-dark)"><?= htmlspecialchars((string)$article['title']) ?></div>
              <div style="font-size:.8rem;color:var(--text-light);margin-top:4px">
                Slug: <code><?= htmlspecialchars((string)$article['slug']) ?></code>
              </div>
            </div>
            <a href="<?= htmlspecialchars(BASE_URL . $currentPagePath . '?id=' . (int)$article['id']) ?>" class="btn btn-outline btn-sm">Edit</a>
          </div>

          <?php if (!empty($article['excerpt'])): ?>
          <p style="margin:10px 0 0;color:var(--text-mid);line-height:1.6"><?= htmlspecialchars((string)$article['excerpt']) ?></p>
          <?php endif; ?>

          <div style="display:flex;flex-wrap:wrap;gap:12px;font-size:.78rem;color:var(--text-light);margin-top:10px">
            <span>Publish: <?= !empty($article['published_at']) ? htmlspecialchars(date('d M Y H:i', strtotime((string)$article['published_at']))) : 'Belum dijadwalkan' ?></span>
            <span>Dibuat: <?= htmlspecialchars(date('d M Y H:i', strtotime((string)$article['created_at']))) ?></span>
          </div>

          <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:12px">
            <?php if ($article['status'] === 'published'): ?>
            <a href="<?= htmlspecialchars($articleUrl) ?>" class="btn btn-outline btn-sm" target="_blank">Lihat Publik</a>
            <?php endif; ?>
            <form method="POST" onsubmit="return confirm('Hapus artikel ini?');">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="delete_article">
              <input type="hidden" name="article_id" value="<?= (int)$article['id'] ?>">
              <button type="submit" class="btn btn-outline btn-sm" style="border-color:#d9a9a9;color:#9a3f3f">Hapus</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
echo View::renderLayout($pageTitle, $content, $role);
