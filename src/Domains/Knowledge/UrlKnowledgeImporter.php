<?php

declare(strict_types=1);

namespace KopiBot\Domains\Knowledge;

class UrlKnowledgeImporter
{
    public function __construct(
        private TextKnowledgeImporter $textImporter = new TextKnowledgeImporter()
    ) {}

    public function importUrl(
        int $tenantId,
        ?int $branchId,
        string $url,
        ?string $title = null,
        array $metadata = []
    ): array {
        $url = trim($url);
        if (!$this->isAllowedUrl($url)) {
            return ['success' => false, 'message' => 'URL is not valid or not allowed'];
        }

        $html = $this->fetchUrl($url);
        if ($html === '') {
            return ['success' => false, 'message' => 'Failed to fetch URL content'];
        }

        $extractedTitle = $this->extractTitle($html);
        $text = $this->htmlToText($html);

        if ($text === '') {
            return ['success' => false, 'message' => 'URL text extraction returned empty content'];
        }

        $title = $title ?: ($extractedTitle ?: $url);

        return $this->textImporter->importText($tenantId, $branchId, $title, $text, array_merge($metadata, [
            'source_type' => 'url',
            'source_url' => $url,
        ]));
    }

    private function isAllowedUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    private function fetchUrl(string $url): string
    {
        if (!function_exists('curl_init')) {
            return '';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT_MS => 5000,
            CURLOPT_TIMEOUT_MS => 15000,
            CURLOPT_USERAGENT => 'KopiBotKnowledgeImporter/1.0',
        ]);

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        curl_close($ch);

        if (!is_string($raw) || $raw === '' || $status >= 400) {
            return '';
        }

        if ($contentType !== '' && !str_contains($contentType, 'text/html') && !str_contains($contentType, 'text/plain')) {
            return '';
        }

        return $raw;
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match) !== 1) {
            return '';
        }

        return trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<\/(p|div|section|article|header|footer|h[1-6]|li|br)>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
