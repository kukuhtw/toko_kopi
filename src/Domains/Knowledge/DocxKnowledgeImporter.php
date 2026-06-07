<?php

declare(strict_types=1);

namespace KopiBot\Domains\Knowledge;

use ZipArchive;

class DocxKnowledgeImporter
{
    public function __construct(
        private TextKnowledgeImporter $textImporter = new TextKnowledgeImporter()
    ) {}

    public function importFile(
        int $tenantId,
        ?int $branchId,
        string $path,
        ?string $title = null,
        array $metadata = []
    ): array {
        if (!is_file($path) || !is_readable($path)) {
            return ['success' => false, 'message' => 'DOCX file is not readable'];
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'docx') {
            return ['success' => false, 'message' => 'File must be a DOCX'];
        }

        $text = $this->extractText($path);
        if (trim($text) === '') {
            return ['success' => false, 'message' => 'DOCX text extraction returned empty content'];
        }

        $title = $title ?: pathinfo($path, PATHINFO_FILENAME);

        return $this->textImporter->importText($tenantId, $branchId, $title, $text, array_merge($metadata, [
            'source_type' => 'docx',
            'filename' => basename($path),
        ]));
    }

    public function extractText(string $path): string
    {
        if (!class_exists(ZipArchive::class)) {
            return '';
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $parts = [];
        foreach (['word/document.xml', 'word/footnotes.xml', 'word/endnotes.xml'] as $entry) {
            $xml = $zip->getFromName($entry);
            if (is_string($xml) && $xml !== '') {
                $parts[] = $this->xmlToText($xml);
            }
        }

        $zip->close();

        return $this->normalize(implode("\n\n", array_filter($parts)));
    }

    private function xmlToText(string $xml): string
    {
        $xml = preg_replace('/<w:tab\/>/i', "\t", $xml) ?? $xml;
        $xml = preg_replace('/<w:br\/>/i', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<\/w:p>/i', "\n", $xml) ?? $xml;
        $text = strip_tags($xml);
        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }
}
