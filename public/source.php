<?php
declare(strict_types=1);

require_once __DIR__ . '/docs/_common.php';

docsSetRenderContext('readme');

$relativePath = str_replace('\\', '/', (string)($_GET['path'] ?? ''));
$relativePath = ltrim($relativePath, '/');
$root = realpath(docsProjectRoot());
$target = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

if ($relativePath === '' || $target === false || $root === false || strncmp($target, $root, strlen($root)) !== 0) {
    http_response_code(404);
    echo docsPageShell(
        'Sumber Tidak Ditemukan',
        'docs-page',
        '<main class="docs-shell"><section class="docs-card docs-main"><div class="docs-empty"><h1>Sumber tidak ditemukan</h1><p>Path yang diminta tidak valid atau berada di luar folder project.</p></div></section></main>',
        'Sumber project tidak ditemukan.'
    );
    exit;
}

$title = 'Source Viewer';
$breadcrumbPath = htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8');
$remark = docsBrandRemarkHtml();

if (is_dir($target)) {
    $items = [];
    $children = scandir($target) ?: [];
    foreach ($children as $child) {
        if ($child === '.' || $child === '..') {
            continue;
        }
        $childRel = trim($relativePath . '/' . $child, '/');
        $label = htmlspecialchars($child . (is_dir($target . DIRECTORY_SEPARATOR . $child) ? '/' : ''), ENT_QUOTES, 'UTF-8');
        $items[] = '<li><a href="' . htmlspecialchars(docsSourceViewerUrl($childRel), ENT_QUOTES, 'UTF-8') . '">' . $label . '</a></li>';
    }

    $listing = $items !== [] ? '<ul>' . implode("\n", $items) . '</ul>' : '<p>Folder kosong.</p>';
    $body = <<<HTML
{$remark}
<main class="docs-shell">
  <aside class="docs-card docs-sidebar">
    <h2>Navigasi</h2>
    <nav>
      <a href="readme.php">README Project</a>
      <a href="docs/index.php">Dokumentasi HTML</a>
      <a class="is-active" href="#">Folder Source</a>
    </nav>
  </aside>
  <article class="docs-card docs-main">
    <div class="docs-kicker">Source Viewer</div>
    <h1>{$breadcrumbPath}</h1>
    <p>Menampilkan isi folder dari project source.</p>
    {$listing}
  </article>
  <aside class="docs-card docs-toc">
    <h2>Aksi</h2>
    <nav>
      <a href="readme.php">Kembali ke README</a>
      <a href="docs/index.php">Buka Docs</a>
    </nav>
  </aside>
</main>
HTML;

    echo docsPageShell($title, 'docs-page', $body, 'Source viewer folder project.');
    exit;
}

$extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));
$content = (string)file_get_contents($target);

if ($extension === 'md') {
    $parsed = docsParseMarkdown($content);
    $rendered = $parsed['html'];
    $toc = docsRenderToc($parsed['headings']);
} else {
    $language = $extension !== '' ? ' class="language-' . htmlspecialchars($extension, ENT_QUOTES, 'UTF-8') . '"' : '';
    $rendered = '<pre><code' . $language . '>' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</code></pre>';
    $toc = '<a href="readme.php">Kembali ke README</a>';
}

$body = <<<HTML
{$remark}
<main class="docs-shell">
  <aside class="docs-card docs-sidebar">
    <h2>Navigasi</h2>
    <nav>
      <a href="readme.php">README Project</a>
      <a href="docs/index.php">Dokumentasi HTML</a>
      <a class="is-active" href="#">File Source</a>
    </nav>
  </aside>
  <article class="docs-card docs-main">
    <div class="docs-kicker">Source Viewer</div>
    <h1>{$breadcrumbPath}</h1>
    {$rendered}
  </article>
  <aside class="docs-card docs-toc">
    <h2>Navigasi</h2>
    <nav>
      {$toc}
    </nav>
  </aside>
</main>
HTML;

echo docsPageShell($title, 'docs-page', $body, 'Source viewer file project.');
