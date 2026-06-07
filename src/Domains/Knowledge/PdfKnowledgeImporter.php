<?php

declare(strict_types=1);

namespace KopiBot\Domains\Knowledge;

class PdfKnowledgeImporter
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
            return ['success' => false, 'message' => 'PDF file is not readable'];
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
            return ['success' => false, 'message' => 'File must be a PDF'];
        }

        $text = $this->extractText($path);
        if (trim($text) === '') {
            return [
                'success' => false,
                'message' => 'PDF text extraction returned empty content. Install pdftotext or provide extracted text manually.',
            ];
        }

        $title = $title ?: pathinfo($path, PATHINFO_FILENAME);

        return $this->textImporter->importText($tenantId, $branchId, $title, $text, array_merge($metadata, [
            'source_type' => 'pdf',
            'filename' => basename($path),
        ]));
    }

    public function extractText(string $path): string
    {
        $pdftotext = $this->findBinary('pdftotext');
        if ($pdftotext === null) {
            return '';
        }

        $command = escapeshellcmd($pdftotext)
            . ' -layout -enc UTF-8 '
            . escapeshellarg($path)
            . ' -';

        $output = shell_exec($command);
        return is_string($output) ? $this->normalize($output) : '';
    }

    private function findBinary(string $binary): ?string
    {
        $paths = [
            '/usr/bin/' . $binary,
            '/usr/local/bin/' . $binary,
            '/opt/homebrew/bin/' . $binary,
        ];

        foreach ($paths as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        $which = shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null');
        $which = is_string($which) ? trim($which) : '';

        return $which !== '' && is_executable($which) ? $which : null;
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }
}
