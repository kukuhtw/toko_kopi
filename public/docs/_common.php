<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Config/config.php';

function docsSourceDir(): string
{
    return dirname(__DIR__, 2) . '/docs';
}

function docsBaseUrl(): string
{
    return BASE_URL . '/docs';
}

function docsProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

function docsPublicUrl(string $path = ''): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    return BASE_URL . ($path !== '' ? '/' . $path : '');
}

function docsSourceViewerUrl(string $path): string
{
    return docsPublicUrl('source.php?path=' . rawurlencode(str_replace('\\', '/', ltrim($path, '/'))));
}

function docsSetRenderContext(string $context): void
{
    $GLOBALS['docs_render_context'] = $context;
}

function docsGetRenderContext(): string
{
    return (string)($GLOBALS['docs_render_context'] ?? 'docs');
}

function docsCatalog(): array
{
    static $catalog = null;

    if ($catalog !== null) {
        return $catalog;
    }

    $preferredOrder = [
        'instalasi',
        'lisensi',
        'plugin-system',
        'tutorial-membuat-plugin',
        'sirclo-full-connector',
        'payment-gateway-midtrans',
        'payment-gateway-ipaymu',
        'payment-gateway-nicepay',
        'delivery-kiriminaja',
        'customer-agent-architecture',
    ];

    $entries = [];
    foreach (glob(docsSourceDir() . '/*.md') ?: [] as $path) {
        $slug = basename($path, '.md');
        $entries[$slug] = [
            'slug' => $slug,
            'path' => $path,
            'title' => docsExtractTitle($path),
            'excerpt' => docsExtractExcerpt($path),
            'url' => docsDocUrl($slug),
        ];
    }

    uksort($entries, function (string $a, string $b) use ($preferredOrder): int {
        $left = array_search($a, $preferredOrder, true);
        $right = array_search($b, $preferredOrder, true);

        if ($left !== false && $right !== false) {
            return $left <=> $right;
        }
        if ($left !== false) {
            return -1;
        }
        if ($right !== false) {
            return 1;
        }

        return strnatcasecmp($a, $b);
    });

    $catalog = $entries;
    return $catalog;
}

function docsDocUrl(string $slug): string
{
    return docsBaseUrl() . '/' . rawurlencode($slug) . '.php';
}

function docsPathForSlug(string $slug): ?string
{
    $catalog = docsCatalog();
    return $catalog[$slug]['path'] ?? null;
}

function docsExtractTitle(string $path): string
{
    foreach (preg_split("/\r\n|\n|\r/", (string) file_get_contents($path)) as $line) {
        if (preg_match('/^#\s+(.+)$/', trim($line), $matches)) {
            return trim($matches[1]);
        }
    }

    return ucwords(str_replace('-', ' ', basename($path, '.md')));
}

function docsExtractExcerpt(string $path): string
{
    $lines = preg_split("/\r\n|\n|\r/", (string) file_get_contents($path));
    $buffer = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || preg_match('/^[-*_]{3,}$/', $trimmed)) {
            if ($buffer !== []) {
                break;
            }
            continue;
        }
        if (preg_match('/^```/', $trimmed)) {
            break;
        }
        if (preg_match('/^\|.*\|$/', $trimmed)) {
            continue;
        }
        $buffer[] = $trimmed;
        if (count($buffer) >= 2) {
            break;
        }
    }

    return trim(implode(' ', $buffer));
}

function docsAnchorSlug(string $text): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/[`*_~\[\]\(\)]/u', '', $text) ?? $text;
    $text = strtolower($text);
    $text = preg_replace('/[^\pL\pN]+/u', '-', $text) ?? $text;
    $text = trim($text, '-');

    return $text !== '' ? $text : 'section';
}

function docsStripMarkdown(string $text): string
{
    $text = preg_replace('/`([^`]+)`/', '$1', $text) ?? $text;
    $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $text) ?? $text;
    $text = preg_replace('/[*_~#>]/', '', $text) ?? $text;
    return trim(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
}

function docsRewriteHref(string $href): string
{
    if ($href === '' || str_starts_with($href, '#')) {
        return $href;
    }

    if (preg_match('#^(https?:|mailto:)#i', $href)) {
        return $href;
    }

    $context = docsGetRenderContext();
    $normalized = str_replace('\\', '/', $href);

    if (preg_match('/(^|\/)README\.md(#.*)?$/i', $normalized, $matches)) {
        $anchor = $matches[2] ?? '';
        return docsPublicUrl('readme.php') . $anchor;
    }

    if (preg_match('/^docs\/([^#?]+)\.md(#.*)?$/i', ltrim($normalized, './'), $matches)) {
        $slug = basename($matches[1]);
        $anchor = $matches[2] ?? '';
        return docsPublicUrl('docs/' . rawurlencode($slug) . '.php' . $anchor);
    }

    if (preg_match('/^([^#?]+)\.md(#.*)?$/i', $normalized, $matches)) {
        $slug = basename($matches[1]);
        $anchor = $matches[2] ?? '';
        if ($context === 'readme') {
            return docsPublicUrl('docs/' . rawurlencode($slug) . '.php' . $anchor);
        }
        return rawurlencode($slug) . '.php' . $anchor;
    }

    if (preg_match('/^public\/(.+)$/i', ltrim($normalized, './'), $matches)) {
        return docsPublicUrl($matches[1]);
    }

    if (preg_match('/^(docs|plugins|database|app|storage|uploads)\/.+$/i', ltrim($normalized, './'))) {
        return docsSourceViewerUrl(ltrim($normalized, './'));
    }

    if ($context === 'readme' && !preg_match('#^(?:[a-z]+:|/)#i', $normalized)) {
        return docsSourceViewerUrl(ltrim($normalized, './'));
    }

    return $href;
}

function docsRenderInline(string $text): string
{
    static $depth = 0;
    $depth++;

    $placeholders = [];

    $text = preg_replace_callback('/`([^`]+)`/', function (array $matches) use (&$placeholders): string {
        $key = '__DOC_CODE_' . count($placeholders) . '__';
        $placeholders[$key] = '<code>' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</code>';
        return $key;
    }, $text) ?? $text;

    $text = preg_replace_callback('/\[(.+?)\]\((.+?)\)/', function (array $matches) use (&$placeholders, $depth): string {
        $key = '__DOC_LINK_' . count($placeholders) . '__';
        $label = $depth < 3
            ? docsRenderInline($matches[1])
            : htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
        $href = docsRewriteHref($matches[2]);
        $placeholders[$key] = '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
        return $key;
    }, $text) ?? $text;

    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text) ?? $text;
    $text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text) ?? $text;
    $text = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $text) ?? $text;
    $text = strtr($text, $placeholders);

    $depth--;
    return $text;
}

function docsTableCells(string $line): array
{
    $trimmed = trim($line);
    $trimmed = trim($trimmed, '|');
    return array_map(static fn(string $cell): string => trim($cell), explode('|', $trimmed));
}

function docsIsTableSeparator(string $line): bool
{
    $trimmed = trim($line);
    return (bool) preg_match('/^\|?[\s:\-]+(?:\|[\s:\-]+)+\|?$/', $trimmed);
}

function docsFlushParagraph(array &$paragraph, array &$html): void
{
    if ($paragraph === []) {
        return;
    }

    $html[] = '<p>' . docsRenderInline(trim(implode(' ', $paragraph))) . '</p>';
    $paragraph = [];
}

function docsCloseList(?string &$listType, array &$html): void
{
    if ($listType === null) {
        return;
    }

    $html[] = $listType === 'ol' ? '</ol>' : '</ul>';
    $listType = null;
}

function docsParseMarkdown(string $markdown): array
{
    $lines = preg_split("/\r\n|\n|\r/", $markdown);
    $html = [];
    $headings = [];
    $paragraph = [];
    $listType = null;
    $inCode = false;
    $codeLang = '';
    $codeLines = [];

    for ($i = 0, $count = count($lines); $i < $count; $i++) {
        $line = $lines[$i];
        $trimmed = trim($line);

        if ($inCode) {
            if (preg_match('/^```/', $trimmed)) {
                $languageClass = $codeLang !== '' ? ' class="language-' . htmlspecialchars($codeLang, ENT_QUOTES, 'UTF-8') . '"' : '';
                $html[] = '<pre><code' . $languageClass . '>' . htmlspecialchars(implode("\n", $codeLines), ENT_QUOTES, 'UTF-8') . '</code></pre>';
                $inCode = false;
                $codeLang = '';
                $codeLines = [];
            } else {
                $codeLines[] = rtrim($line, "\r");
            }
            continue;
        }

        if (preg_match('/^```([\w+-]*)\s*$/', $trimmed, $matches)) {
            docsFlushParagraph($paragraph, $html);
            docsCloseList($listType, $html);
            $inCode = true;
            $codeLang = trim($matches[1]);
            $codeLines = [];
            continue;
        }

        if ($trimmed === '') {
            docsFlushParagraph($paragraph, $html);
            docsCloseList($listType, $html);
            continue;
        }

        if (
            str_contains($trimmed, '|') &&
            isset($lines[$i + 1]) &&
            docsIsTableSeparator($lines[$i + 1])
        ) {
            docsFlushParagraph($paragraph, $html);
            docsCloseList($listType, $html);

            $headerCells = docsTableCells($line);
            $rows = [];
            $i += 2;

            while ($i < $count) {
                $rowLine = trim($lines[$i]);
                if ($rowLine === '' || !str_contains($rowLine, '|')) {
                    $i--;
                    break;
                }
                $rows[] = docsTableCells($lines[$i]);
                $i++;
            }

            $html[] = '<div class="doc-table-wrap"><table class="doc-table"><thead><tr>';
            foreach ($headerCells as $cell) {
                $html[] = '<th>' . docsRenderInline($cell) . '</th>';
            }
            $html[] = '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html[] = '<tr>';
                foreach ($row as $cell) {
                    $html[] = '<td>' . docsRenderInline($cell) . '</td>';
                }
                $html[] = '</tr>';
            }
            $html[] = '</tbody></table></div>';
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches)) {
            docsFlushParagraph($paragraph, $html);
            docsCloseList($listType, $html);

            $level = strlen($matches[1]);
            $label = trim($matches[2]);
            $id = docsAnchorSlug(docsStripMarkdown($label));

            $headings[] = [
                'level' => $level,
                'id' => $id,
                'label' => docsStripMarkdown($label),
            ];
            $html[] = sprintf(
                '<h%d id="%s">%s</h%d>',
                $level,
                htmlspecialchars($id, ENT_QUOTES, 'UTF-8'),
                docsRenderInline($label),
                $level
            );
            continue;
        }

        if (preg_match('/^[-*_]{3,}$/', $trimmed)) {
            docsFlushParagraph($paragraph, $html);
            docsCloseList($listType, $html);
            $html[] = '<hr>';
            continue;
        }

        if (preg_match('/^>\s?(.*)$/', $trimmed, $matches)) {
            docsFlushParagraph($paragraph, $html);
            docsCloseList($listType, $html);

            $quoteLines = [$matches[1]];
            while (isset($lines[$i + 1]) && preg_match('/^>\s?(.*)$/', trim($lines[$i + 1]), $nextQuote)) {
                $quoteLines[] = $nextQuote[1];
                $i++;
            }

            $html[] = '<blockquote><p>' . docsRenderInline(trim(implode(' ', $quoteLines))) . '</p></blockquote>';
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $matches)) {
            docsFlushParagraph($paragraph, $html);
            if ($listType !== 'ul') {
                docsCloseList($listType, $html);
                $html[] = '<ul>';
                $listType = 'ul';
            }
            $html[] = '<li>' . docsRenderInline($matches[1]) . '</li>';
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $matches)) {
            docsFlushParagraph($paragraph, $html);
            if ($listType !== 'ol') {
                docsCloseList($listType, $html);
                $html[] = '<ol>';
                $listType = 'ol';
            }
            $html[] = '<li>' . docsRenderInline($matches[1]) . '</li>';
            continue;
        }

        docsCloseList($listType, $html);
        $paragraph[] = $trimmed;
    }

    if ($inCode) {
        $languageClass = $codeLang !== '' ? ' class="language-' . htmlspecialchars($codeLang, ENT_QUOTES, 'UTF-8') . '"' : '';
        $html[] = '<pre><code' . $languageClass . '>' . htmlspecialchars(implode("\n", $codeLines), ENT_QUOTES, 'UTF-8') . '</code></pre>';
    }

    docsFlushParagraph($paragraph, $html);
    docsCloseList($listType, $html);

    return [
        'html' => implode("\n", $html),
        'headings' => $headings,
    ];
}

function docsRenderSidebarLinks(array $catalog, string $activeSlug): string
{
    $items = [];
    foreach ($catalog as $slug => $entry) {
        $class = $slug === $activeSlug ? ' class="is-active"' : '';
        $items[] = '<a' . $class . ' href="' . htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8')
            . '</a>';
    }

    return implode("\n", $items);
}

function docsRenderToc(array $headings): string
{
    $items = [];
    foreach ($headings as $heading) {
        if ($heading['level'] > 3) {
            continue;
        }
        $items[] = '<a class="toc-level-' . (int) $heading['level'] . '" href="#'
            . htmlspecialchars($heading['id'], ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($heading['label'], ENT_QUOTES, 'UTF-8') . '</a>';
    }

    return implode("\n", $items);
}

function docsBrandRemarkHtml(): string
{
    return <<<HTML
<section class="docs-brand-remark-wrap">
  <div class="docs-brand-remark">
    <div class="docs-brand-remark-kicker">&#9749; AI Agent Coffee Shop Commerce Platform</div>
    <p>Platform AI untuk otomatisasi order, customer service, loyalty customer, Customer CRM, Customer Portal, dan manajemen multi cabang coffee shop.</p>
    <div class="docs-brand-remark-grid">
      <div>
        <h3>&#128640; Features</h3>
        <ul>
          <li>AI Chatbot Order Menu</li>
          <li>WhatsApp / Telegram / Discord Integration</li>
          <li>Multi Branch Management</li>
          <li>AI Upselling &amp; Promo Recommendation</li>
          <li>Order via Website &amp; Chat Apps</li>
          <li>Variant Product &amp; Topping Support</li>
          <li>Loyalty Point, Customer CRM, dan Customer Portal</li>
          <li>Multi Currency, Tax &amp; Timezone</li>
          <li>AI Customer Interaction Automation</li>
        </ul>
      </div>
      <div>
        <h3>&#128187; Tech Stack</h3>
        <p>PHP Native &bull; MySQL &bull; OpenAI &bull; Anthropic<br>WhatsApp Gateway &bull; REST API &bull; LLM AI</p>
        <h3>&#9749; Suitable For</h3>
        <p>Coffee Shop &bull; Cafe &bull; Restaurant &bull; Bakery &bull; Beverage Store</p>
      </div>
      <div>
        <h3>Dibuat &amp; Dikembangkan oleh</h3>
        <p>Kukuh TW</p>
        <p>&#128231; Email: <a href="mailto:kukuhtw@gmail.com">kukuhtw@gmail.com</a></p>
        <p>&#128241; WhatsApp: <a href="https://wa.me/628129893706" target="_blank" rel="noopener">wa.me/628129893706</a></p>
        <p>&#127748; Instagram: @kukuhtw</p>
        <p>X/Twitter: @kukuhtw</p>
        <p>Facebook: <a href="https://www.facebook.com/kukuhtw" target="_blank" rel="noopener">facebook.com/kukuhtw</a></p>
        <p>LinkedIn: <a href="https://linkedin.com/in/kukuhtw" target="_blank" rel="noopener">linkedin.com/in/kukuhtw</a></p>
        <p>GitHub: <a href="https://github.com/kukuhtw/toko_kopi" target="_blank" rel="noopener">github.com/kukuhtw/toko_kopi</a></p>
        <p>&#127760; Demo: <a href="https://botlelang.com/toko_kopi" target="_blank" rel="noopener">botlelang.com/toko_kopi</a></p>
        <p>&copy; 2026 Kukuh TW. All rights reserved.</p>
      </div>
    </div>
  </div>
</section>
HTML;
}

function docsPageShell(string $title, string $bodyClass, string $mainContent, string $metaDescription = ''): string
{
    $pageTitle = htmlspecialchars($title . ' | Dokumentasi KopiBot AI', ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars($metaDescription !== '' ? $metaDescription : 'Dokumentasi HTML KopiBot AI.', ENT_QUOTES, 'UTF-8');
    $homeUrl = htmlspecialchars(BASE_URL . '/index.php', ENT_QUOTES, 'UTF-8');
    $docsHomeUrl = htmlspecialchars(docsBaseUrl() . '/index.php', ENT_QUOTES, 'UTF-8');
    $assetUrl = htmlspecialchars(BASE_URL . '/assets/css/app.css', ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$pageTitle}</title>
  <meta name="description" content="{$description}">
  <link rel="stylesheet" href="{$assetUrl}">
  <style>
    :root {
      --doc-bg: #f6efe8;
      --doc-surface: #fffdf9;
      --doc-border: rgba(85, 56, 33, 0.14);
      --doc-text: #4d3425;
      --doc-text-soft: #745842;
      --doc-accent: #8b5e3c;
      --doc-accent-strong: #5f3b24;
      --doc-code-bg: #2b211b;
      --doc-code-text: #f8e7d5;
    }
    * { box-sizing: border-box; }
    body.{$bodyClass} {
      margin: 0;
      font-family: "Segoe UI", Arial, sans-serif;
      background:
        radial-gradient(circle at top right, rgba(200,146,42,0.12), transparent 30%),
        linear-gradient(180deg, #fff8f1 0%, var(--doc-bg) 100%);
      color: var(--doc-text);
    }
    .docs-topbar {
      position: sticky;
      top: 0;
      z-index: 20;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      padding: 14px 22px;
      background: rgba(255, 253, 249, 0.94);
      backdrop-filter: blur(8px);
      border-bottom: 1px solid var(--doc-border);
    }
    .docs-brand {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 10px;
      color: var(--doc-accent-strong);
      font-weight: 700;
    }
    .docs-brand a,
    .docs-actions a {
      color: inherit;
      text-decoration: none;
    }
    .docs-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
    }
    .docs-link-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 14px;
      border-radius: 999px;
      background: #fff;
      border: 1px solid var(--doc-border);
      color: var(--doc-accent-strong);
      font-size: 0.88rem;
      font-weight: 600;
    }
    .docs-shell {
      max-width: 1320px;
      margin: 0 auto;
      padding: 28px 20px 48px;
      display: grid;
      grid-template-columns: minmax(0, 260px) minmax(0, 1fr) minmax(0, 240px);
      gap: 22px;
    }
    .docs-brand-remark-wrap {
      max-width: 1320px;
      margin: 0 auto;
      padding: 24px 20px 0;
    }
    .docs-brand-remark {
      background: linear-gradient(135deg, rgba(91, 56, 31, 0.98), rgba(139, 94, 60, 0.96));
      color: #fff8f1;
      border-radius: 28px;
      border: 1px solid rgba(255, 232, 204, 0.18);
      box-shadow: 0 18px 38px rgba(91, 61, 40, 0.16);
      padding: 28px;
    }
    .docs-brand-remark-kicker {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      border-radius: 999px;
      background: rgba(255, 222, 173, 0.14);
      border: 1px solid rgba(255, 222, 173, 0.22);
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }
    .docs-brand-remark > p {
      margin: 16px 0 0;
      max-width: 760px;
      line-height: 1.75;
      color: rgba(255, 248, 241, 0.92);
    }
    .docs-brand-remark-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 18px;
      margin-top: 22px;
    }
    .docs-brand-remark-grid > div {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 20px;
      padding: 18px 18px 16px;
    }
    .docs-brand-remark-grid h3 {
      margin: 0 0 10px;
      font-size: 0.95rem;
      color: #ffe0ab;
    }
    .docs-brand-remark-grid p,
    .docs-brand-remark-grid li {
      color: rgba(255, 248, 241, 0.92);
      line-height: 1.7;
      font-size: 0.92rem;
    }
    .docs-brand-remark-grid ul {
      margin: 0;
      padding-left: 18px;
    }
    .docs-brand-remark-grid a {
      color: #ffe0ab;
    }
    .docs-card {
      background: var(--doc-surface);
      border: 1px solid var(--doc-border);
      border-radius: 22px;
      box-shadow: 0 12px 30px rgba(91, 61, 40, 0.08);
    }
    .docs-sidebar,
    .docs-toc {
      padding: 20px;
      position: sticky;
      top: 82px;
      align-self: start;
    }
    .docs-sidebar h2,
    .docs-toc h2 {
      margin: 0 0 14px;
      font-size: 0.95rem;
      color: var(--doc-accent-strong);
    }
    .docs-sidebar nav,
    .docs-toc nav {
      display: grid;
      gap: 8px;
    }
    .docs-sidebar nav a,
    .docs-toc nav a {
      text-decoration: none;
      color: var(--doc-text-soft);
      padding: 10px 12px;
      border-radius: 12px;
      transition: background 0.2s ease, color 0.2s ease;
      font-size: 0.9rem;
      line-height: 1.45;
    }
    .docs-sidebar nav a:hover,
    .docs-sidebar nav a.is-active,
    .docs-toc nav a:hover {
      background: rgba(139, 94, 60, 0.09);
      color: var(--doc-accent-strong);
    }
    .docs-toc nav a.toc-level-3 {
      padding-left: 22px;
      font-size: 0.85rem;
    }
    .docs-main {
      padding: 28px 30px 34px;
      overflow: hidden;
    }
    .docs-kicker {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 16px;
      padding: 7px 13px;
      border-radius: 999px;
      background: rgba(200, 146, 42, 0.12);
      color: var(--doc-accent-strong);
      font-weight: 700;
      font-size: 0.78rem;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }
    .docs-main h1,
    .docs-main h2,
    .docs-main h3,
    .docs-main h4,
    .docs-main h5,
    .docs-main h6 {
      color: var(--doc-accent-strong);
      line-height: 1.2;
      scroll-margin-top: 96px;
    }
    .docs-main h1 { font-size: clamp(2rem, 4vw, 2.7rem); margin: 0 0 18px; }
    .docs-main h2 { font-size: 1.45rem; margin: 32px 0 12px; }
    .docs-main h3 { font-size: 1.15rem; margin: 24px 0 10px; }
    .docs-main p,
    .docs-main li,
    .docs-main blockquote {
      color: var(--doc-text);
      line-height: 1.75;
      font-size: 0.96rem;
    }
    .docs-main p { margin: 0 0 14px; }
    .docs-main ul,
    .docs-main ol {
      margin: 0 0 16px 20px;
      padding: 0;
    }
    .docs-main li + li { margin-top: 6px; }
    .docs-main hr {
      border: 0;
      border-top: 1px solid var(--doc-border);
      margin: 26px 0;
    }
    .docs-main code {
      font-family: Consolas, "Courier New", monospace;
      background: rgba(139, 94, 60, 0.1);
      color: var(--doc-accent-strong);
      padding: 0.15rem 0.4rem;
      border-radius: 8px;
      font-size: 0.9em;
    }
    .docs-main pre {
      margin: 18px 0 22px;
      padding: 18px 20px;
      background: var(--doc-code-bg);
      color: var(--doc-code-text);
      border-radius: 18px;
      overflow: auto;
      border: 1px solid rgba(255, 255, 255, 0.05);
    }
    .docs-main pre code {
      background: transparent;
      color: inherit;
      padding: 0;
      border-radius: 0;
      display: block;
      white-space: pre;
    }
    .docs-main blockquote {
      margin: 18px 0;
      padding: 14px 18px;
      border-left: 4px solid rgba(139, 94, 60, 0.4);
      background: rgba(139, 94, 60, 0.07);
      border-radius: 0 16px 16px 0;
    }
    .docs-main a {
      color: var(--doc-accent);
      text-decoration: none;
      font-weight: 600;
    }
    .docs-main a:hover { text-decoration: underline; }
    .doc-table-wrap {
      overflow: auto;
      margin: 18px 0 22px;
      border: 1px solid var(--doc-border);
      border-radius: 18px;
      background: #fff;
    }
    .doc-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 520px;
    }
    .doc-table th,
    .doc-table td {
      padding: 12px 14px;
      border-bottom: 1px solid rgba(85, 56, 33, 0.08);
      text-align: left;
      vertical-align: top;
      font-size: 0.92rem;
    }
    .doc-table th {
      background: rgba(139, 94, 60, 0.08);
      color: var(--doc-accent-strong);
      font-weight: 700;
    }
    .doc-table tr:last-child td { border-bottom: 0; }
    .docs-empty {
      padding: 36px;
      text-align: center;
      color: var(--doc-text-soft);
    }
    .docs-listing {
      display: grid;
      gap: 16px;
    }
    .docs-listing-item {
      padding: 20px 22px;
      border-radius: 20px;
      border: 1px solid var(--doc-border);
      background: linear-gradient(180deg, rgba(255,255,255,0.92), rgba(255,248,241,0.98));
    }
    .docs-listing-item h3 {
      margin: 0 0 8px;
      color: var(--doc-accent-strong);
      font-size: 1.05rem;
    }
    .docs-listing-item p {
      margin: 0 0 14px;
      color: var(--doc-text-soft);
      line-height: 1.7;
    }
    .docs-listing-item a {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      color: var(--doc-accent-strong);
      font-weight: 700;
    }
    @media (max-width: 1100px) {
      .docs-brand-remark-grid {
        grid-template-columns: minmax(0, 1fr);
      }
      .docs-shell {
        grid-template-columns: minmax(0, 1fr);
      }
      .docs-sidebar,
      .docs-toc {
        position: static;
      }
      .docs-toc {
        order: 3;
      }
    }
    @media (max-width: 720px) {
      .docs-brand-remark-wrap {
        padding: 18px 14px 0;
      }
      .docs-brand-remark {
        padding: 22px 18px;
        border-radius: 22px;
      }
      .docs-topbar {
        padding: 14px 16px;
        align-items: flex-start;
        flex-direction: column;
      }
      .docs-shell {
        padding: 20px 14px 36px;
      }
      .docs-main {
        padding: 22px 18px 26px;
      }
      .docs-actions {
        width: 100%;
      }
    }
  </style>
</head>
<body class="{$bodyClass}">
  <header class="docs-topbar">
    <div class="docs-brand">
      <a href="{$homeUrl}">KopiBot AI</a>
      <span>/</span>
      <a href="{$docsHomeUrl}">Dokumentasi HTML</a>
    </div>
    <div class="docs-actions">
      <a class="docs-link-chip" href="{$docsHomeUrl}">Semua Dokumen</a>
      <a class="docs-link-chip" href="https://github.com/kukuhtw/toko_kopi" target="_blank" rel="noopener noreferrer">GitHub Repo</a>
      <a class="docs-link-chip" href="{$homeUrl}">Kembali ke Landing Page</a>
    </div>
  </header>
  {$mainContent}
</body>
</html>
HTML;
}

function docsRenderPage(string $slug): string
{
    $catalog = docsCatalog();
    if (!isset($catalog[$slug])) {
        http_response_code(404);
        $body = '<main class="docs-shell"><section class="docs-card docs-main"><div class="docs-empty"><h1>Dokumen tidak ditemukan</h1><p>Slug <code>'
            . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8')
            . '</code> tidak ada di folder <code>/docs</code>.</p></div></section></main>';
        return docsPageShell('Dokumen Tidak Ditemukan', 'docs-page', $body, 'Dokumen tidak ditemukan.');
    }

    $entry = $catalog[$slug];
    $parsed = docsParseMarkdown((string) file_get_contents($entry['path']));
    $sidebarLinks = docsRenderSidebarLinks($catalog, $slug);
    $tocLinks = docsRenderToc($parsed['headings']);
    $excerpt = $entry['excerpt'] !== '' ? '<p>' . htmlspecialchars($entry['excerpt'], ENT_QUOTES, 'UTF-8') . '</p>' : '';
    $remark = docsBrandRemarkHtml();

    $body = <<<HTML
{$remark}
<main class="docs-shell">
  <aside class="docs-card docs-sidebar">
    <h2>Dokumen</h2>
    <nav>
      {$sidebarLinks}
    </nav>
  </aside>
  <article class="docs-card docs-main">
    <div class="docs-kicker">Markdown ke HTML</div>
    {$excerpt}
    {$parsed['html']}
  </article>
  <aside class="docs-card docs-toc">
    <h2>Navigasi Halaman</h2>
    <nav>
      {$tocLinks}
    </nav>
  </aside>
</main>
HTML;

    return docsPageShell($entry['title'], 'docs-page', $body, $entry['excerpt']);
}

function docsRenderIndexPage(): string
{
    $catalog = docsCatalog();
    $items = [];

    foreach ($catalog as $entry) {
        $excerpt = htmlspecialchars($entry['excerpt'] !== '' ? $entry['excerpt'] : 'Dokumen ini belum memiliki ringkasan otomatis.', ENT_QUOTES, 'UTF-8');
        $items[] = '<article class="docs-listing-item">'
            . '<h3>' . htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8') . '</h3>'
            . '<p>' . $excerpt . '</p>'
            . '<a href="' . htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8') . '">Buka versi HTML</a>'
            . '</article>';
    }

    $listing = $items !== [] ? implode("\n", $items) : '<div class="docs-empty">Belum ada file Markdown di folder <code>/docs</code>.</div>';
    $remark = docsBrandRemarkHtml();

    $body = <<<HTML
{$remark}
<main class="docs-shell">
  <aside class="docs-card docs-sidebar">
    <h2>Koleksi Docs</h2>
    <nav>
      <a class="is-active" href="index.php">Beranda Dokumentasi</a>
      <a href="../readme.php">README Project</a>
      <a href="https://github.com/kukuhtw/toko_kopi" target="_blank" rel="noopener noreferrer">GitHub Repository</a>
      {docs_sidebar_links}
    </nav>
  </aside>
  <section class="docs-card docs-main">
    <div class="docs-kicker">Dokumentasi Developer</div>
    <h1>Dokumentasi HTML KopiBot AI</h1>
    <p>Semua file di folder <code>/docs</code> sekarang punya versi HTML yang lebih nyaman dibaca di browser. Konten sumbernya tetap Markdown, jadi dokumentasi tetap satu sumber dan lebih mudah dirawat, termasuk update terbaru untuk loyalty, Customer CRM, dan Customer Portal.</p>
    <div class="docs-listing">
      {$listing}
    </div>
  </section>
  <aside class="docs-card docs-toc">
    <h2>Isi Cepat</h2>
    <nav>
      <a href="../readme.php">README Project</a>
      <a href="https://github.com/kukuhtw/toko_kopi" target="_blank" rel="noopener noreferrer">GitHub Repository</a>
      <a href="instalasi.php">Panduan Instalasi</a>
      <a href="lisensi.php">Lisensi AGPL + Commercial</a>
      <a href="plugin-system.php">Plugin System</a>
      <a href="sirclo-full-connector.php">Tutorial Integrasi SIRCLO</a>
      <a href="tutorial-membuat-plugin.php">Tutorial Membuat Plugin</a>
    </nav>
  </aside>
</main>
HTML;

    $body = str_replace('{docs_sidebar_links}', docsRenderSidebarLinks($catalog, ''), $body);
    return docsPageShell('Dokumentasi HTML', 'docs-index', $body, 'Pusat dokumentasi HTML KopiBot AI.');
}
