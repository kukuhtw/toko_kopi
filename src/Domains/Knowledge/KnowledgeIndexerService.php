<?php

declare(strict_types=1);

namespace KopiBot\Domains\Knowledge;

use KopiBot\Core\EmbeddingProviderRegistry;
use KopiBot\Core\VectorStoreRegistry;

class KnowledgeIndexerService
{
    public function __construct(
        private KnowledgeChunker $chunker = new KnowledgeChunker()
    ) {}

    public function index(
        string $namespace,
        string $documentId,
        string $title,
        string $content,
        array $metadata = []
    ): array {
        $embedding = EmbeddingProviderRegistry::preferred();
        $vectorStore = VectorStoreRegistry::preferred();

        if ($embedding === null) {
            return ['success' => false, 'message' => 'Embedding provider not available'];
        }

        if ($vectorStore === null) {
            return ['success' => false, 'message' => 'Vector store not available'];
        }

        $chunks = $this->chunker->chunk($content);
        $indexed = 0;

        foreach ($chunks as $index => $chunk) {
            $vector = $embedding->embed($chunk);
            if ($vector === []) {
                continue;
            }

            $id = $documentId . '-chunk-' . ($index + 1);

            $vectorStore->upsert(
                $namespace,
                $id,
                $vector,
                array_merge($metadata, [
                    'document_id' => $documentId,
                    'title' => $title,
                    'chunk_no' => $index + 1,
                    'content' => $chunk,
                ])
            );

            $indexed++;
        }

        return [
            'success' => true,
            'document_id' => $documentId,
            'chunks' => count($chunks),
            'indexed' => $indexed,
            'embedding_provider' => $embedding->getName(),
            'vector_store' => $vectorStore->getName(),
        ];
    }
}
