<?php
declare(strict_types=1);

require_once __DIR__ . '/docs/_common.php';

docsSetRenderContext('readme');

$readmePath = dirname(__DIR__) . '/README.md';

if (!is_file($readmePath)) {
    http_response_code(404);
    echo docsPageShell(
        'README Tidak Ditemukan',
        'docs-page',
        '<main class="docs-shell"><section class="docs-card docs-main"><div class="docs-empty"><h1>README tidak ditemukan</h1><p>File <code>README.md</code> tidak ada di root project.</p></div></section></main>',
        'README project tidak ditemukan.'
    );
    exit;
}

$parsed = docsParseMarkdown((string) file_get_contents($readmePath));
$readmeHtml = preg_replace_callback('/<a\b([^>]*)href="([^"]+)"([^>]*)>(.*?)<\/a>/is', static function (array $matches): string {
    $href = $matches[2];
    if (preg_match('#^(https?:|mailto:)#i', $href)) {
        return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . $matches[4] . '</a>';
    }

    return $matches[4];
}, $parsed['html']) ?? $parsed['html'];
$remark = docsBrandRemarkHtml();
$homeUrl = htmlspecialchars(BASE_URL . '/index.php', ENT_QUOTES, 'UTF-8');
$docsUrl = htmlspecialchars(BASE_URL . '/docs/index.php', ENT_QUOTES, 'UTF-8');
$githubUrl = 'https://github.com/kukuhtw/toko_kopi';

$body = <<<HTML
{$remark}
<main class="docs-shell">
  <aside class="docs-card docs-sidebar">
    <h2>Navigasi</h2>
    <nav>
      <a class="is-active" href="readme.php">README Project</a>
      <a href="{$docsUrl}">Dokumentasi HTML</a>
      <a href="{$githubUrl}" target="_blank" rel="noopener noreferrer">GitHub Repository</a>
      <a href="{$homeUrl}">Landing Page</a>
    </nav>
  </aside>
  <article class="docs-card docs-main">
    <div class="docs-kicker">README Project</div>
    {$readmeHtml}
  </article>
  <aside class="docs-card docs-toc">
    <h2>Isi README</h2>
    <nav>
      {toc_links}
    </nav>
  </aside>
</main>
HTML;

$body = str_replace('{toc_links}', docsRenderToc($parsed['headings']), $body);

echo docsPageShell('README Project', 'docs-page', $body, 'Versi HTML dari README project KopiBot AI.');
