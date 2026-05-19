<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Config/config.php';
require_once __DIR__ . '/NewsCmsRepository.php';

$repo = new NewsCmsRepository();
$branchId = isset($_GET['branch_id']) ? max(0, (int)$_GET['branch_id']) : null;
$slug = trim((string)($_GET['slug'] ?? ''));
$article = $slug !== '' ? $repo->findPublishedBySlug($slug, $branchId) : false;
$articles = $slug === '' ? $repo->getPublishedArticles($branchId) : [];
$pageTitle = $article ? (string)$article['title'] : 'Berita Toko';

$formatDate = static function (?string $value): string {
    if (!$value) {
        return '-';
    }
    $ts = strtotime($value);
    return $ts ? date('d M Y H:i', $ts) : '-';
};

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Berita Toko</title>
  <style>
    :root {
      --ink: #2f241d;
      --soft: #7f6a5b;
      --line: #e7d8ca;
      --paper: #fffaf5;
      --panel: #ffffff;
      --accent: #8b4f2d;
      --accent-soft: #f2e2d5;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Georgia, "Times New Roman", serif;
      color: var(--ink);
      background:
        radial-gradient(circle at top left, #f7e9db 0, transparent 34%),
        linear-gradient(180deg, #fffdfa 0%, var(--paper) 100%);
    }
    .wrap { max-width: 1080px; margin: 0 auto; padding: 28px 18px 60px; }
    .hero, .card, .article {
      background: rgba(255,255,255,.92);
      border: 1px solid var(--line);
      border-radius: 20px;
      box-shadow: 0 18px 50px rgba(82, 47, 24, .08);
    }
    .hero { padding: 28px; margin-bottom: 20px; }
    .eyebrow {
      display: inline-block;
      margin-bottom: 10px;
      padding: 6px 10px;
      border-radius: 999px;
      background: var(--accent-soft);
      color: var(--accent);
      font: 600 12px/1.2 Arial, sans-serif;
      letter-spacing: .08em;
      text-transform: uppercase;
    }
    h1 { margin: 0 0 10px; font-size: clamp(2rem, 4vw, 3.4rem); line-height: 1.05; }
    .lead { margin: 0; max-width: 720px; color: var(--soft); font: 400 1.05rem/1.7 Arial, sans-serif; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
    .card { padding: 18px; }
    .card img, .article img {
      width: 100%;
      border-radius: 16px;
      display: block;
      margin-bottom: 16px;
      object-fit: cover;
      max-height: 320px;
      background: #f2ede8;
    }
    .meta {
      display: flex;
      flex-wrap: wrap;
      gap: 8px 12px;
      color: var(--soft);
      font: 400 .9rem/1.5 Arial, sans-serif;
      margin-bottom: 10px;
    }
    .pill {
      display: inline-block;
      padding: 5px 9px;
      border-radius: 999px;
      background: var(--accent-soft);
      color: var(--accent);
      font: 600 12px/1.2 Arial, sans-serif;
    }
    .card h2, .article h1 { margin: 0 0 10px; }
    .card p, .article-body {
      color: #4b4038;
      font: 400 1rem/1.8 Arial, sans-serif;
    }
    .card a, .back-link {
      color: var(--accent);
      text-decoration: none;
      font-weight: 700;
    }
    .article { padding: 28px; }
    .article-body { white-space: pre-wrap; }
    .empty {
      padding: 28px;
      text-align: center;
      color: var(--soft);
      font: 400 1rem/1.7 Arial, sans-serif;
    }
    @media (max-width: 720px) {
      .hero, .article { padding: 20px; }
      .wrap { padding: 18px 14px 40px; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <?php if ($article): ?>
    <div class="article">
      <div class="eyebrow">Berita Toko</div>
      <div style="margin-bottom:12px"><a class="back-link" href="<?= htmlspecialchars(BASE_URL) ?>/berita.php">&larr; Kembali ke semua berita</a></div>
      <h1><?= htmlspecialchars((string)$article['title']) ?></h1>
      <div class="meta">
        <span><?= htmlspecialchars($formatDate((string)$article['published_at'])) ?></span>
        <span><?= htmlspecialchars((string)($article['branch_name'] ?? 'Global')) ?></span>
        <?php if (!empty($article['is_featured'])): ?><span class="pill">Unggulan</span><?php endif; ?>
      </div>
      <?php if (!empty($article['cover_image'])): ?>
      <img src="<?= htmlspecialchars((string)$article['cover_image']) ?>" alt="<?= htmlspecialchars((string)$article['title']) ?>">
      <?php endif; ?>
      <?php if (!empty($article['excerpt'])): ?>
      <p style="font:italic 1.08rem/1.8 Georgia, serif;color:#6a594d"><?= htmlspecialchars((string)$article['excerpt']) ?></p>
      <?php endif; ?>
      <div class="article-body"><?= nl2br(htmlspecialchars((string)$article['content'])) ?></div>
    </div>
    <?php else: ?>
    <div class="hero">
      <div class="eyebrow">Newsroom</div>
      <h1>Berita dan Cerita dari Toko</h1>
      <p class="lead">
        Kumpulan update terbaru tentang promo, event, menu baru, dan pengumuman penting dari toko.
      </p>
    </div>

    <?php if (empty($articles)): ?>
    <div class="card empty">
      Belum ada berita yang dipublikasikan.
    </div>
    <?php else: ?>
    <div class="grid">
      <?php foreach ($articles as $item): ?>
      <article class="card">
        <?php if (!empty($item['cover_image'])): ?>
        <img src="<?= htmlspecialchars((string)$item['cover_image']) ?>" alt="<?= htmlspecialchars((string)$item['title']) ?>">
        <?php endif; ?>
        <div class="meta">
          <span><?= htmlspecialchars($formatDate((string)$item['published_at'])) ?></span>
          <span><?= htmlspecialchars((string)($item['branch_name'] ?? 'Global')) ?></span>
          <?php if (!empty($item['is_featured'])): ?><span class="pill">Unggulan</span><?php endif; ?>
        </div>
        <h2><?= htmlspecialchars((string)$item['title']) ?></h2>
        <p><?= htmlspecialchars((string)($item['excerpt'] ?: 'Klik untuk membaca selengkapnya.')) ?></p>
        <a href="<?= htmlspecialchars(BASE_URL) ?>/berita.php?slug=<?= urlencode((string)$item['slug']) ?>">Baca selengkapnya</a>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>
