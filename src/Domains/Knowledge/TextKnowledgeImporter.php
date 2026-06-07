<?php

declare(strict_types=1);

namespace KopiBot\Domains\Knowledge;

class TextKnowledgeImporter
{
    public function importText(
        int $tenantId,
        ?int $branchId,
        string $title,
        string $content,
        array $metadata = []
    ): array {
        $title = trim($title);
        $content = $this->normalize($content);

        if ($title === '') {
            return ['success' => false, 'message' => 'Title is required'];
        }

        if ($content === '') {
            return ['success' => false, 'message' => 'Content is required'];
        }

        $documentId = $metadata['document_id'] ?? $this->makeDocumentId($tenantId, $branchId, $title, $content);
        $namespace = $metadata['namespace'] ?? $this->makeNamespace($tenantId, $branchId);

        $indexer = new KnowledgeIndexerService();
        $indexResult = $indexer->index(
            (string)$namespace,
            (string)$documentId,
            $title,
            $content,
            array_merge($metadata, [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'title' => $title,
                'source_type' => $metadata['source_type'] ?? 'text',
            ])
        );

        return array_merge($indexResult, [
            'document_id' => (string)$documentId,
            'namespace' => (string)$namespace,
            'title' => $title,
        ]);
    }

    public function importFile(
        int $tenantId,
        ?int $branchId,
        string $path,
        ?string $title = null,
        array $metadata = []
    ): array {
        if (!is_file($path) || !is_readable($path)) {
            return ['success' => false, 'message' => 'File is not readable'];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return ['success' => false, 'message' => 'Failed to read file'];
        }

        $title = $title ?: pathinfo($path, PATHINFO_FILENAME);

        return $this->importText($tenantId, $branchId, $title, $content, array_merge($metadata, [
            'source_type' => 'file',
            'filename' => basename($path),
        ]));
    }

    private function normalize(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = strip_tags($content);
        $content = preg_replace('/[ \t]+/', ' ', $content) ?? $content;
        $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;
        return trim($content);
    }

    private function makeNamespace(int $tenantId, ?int $branchId): string
    {
        return $branchId !== null && $branchId > 0
            ? 'tenant-' . $tenantId . '-branch-' . $branchId
            : 'tenant-' . $tenantId;
    }

    private function makeDocumentId(int $tenantId, ?int $branchId, string $title, string $content): string
    {
        return hash('sha256', implode('|', [
            $tenantId,
            $branchId ?? 0,
            mb_strtolower($title, 'UTF-8'),
            mb_substr($content, 0, 500),
        ]));
    }
}
