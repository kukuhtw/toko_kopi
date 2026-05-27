<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use PDO;

class PharmacySemanticSearchService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getInstance();
    }

    public function search(string $query, int $limit = 20): array
    {
        $query = strtolower(trim($query));

        $stmt = $this->pdo->prepare(
            "SELECT mi.id, mi.name, mi.description,
                    pm.generic_name,
                    pm.dosage,
                    pm.manufacturer,
                    pm.requires_prescription
             FROM menu_items mi
             LEFT JOIN pharmacy_product_metadata pm ON pm.menu_item_id = mi.id
             WHERE LOWER(mi.name) LIKE :query
                OR LOWER(mi.description) LIKE :query
                OR LOWER(COALESCE(pm.generic_name, '')) LIKE :query
                OR LOWER(COALESCE(pm.manufacturer, '')) LIKE :query
             LIMIT {$limit}"
        );

        $stmt->execute([
            ':query' => '%' . $query . '%'
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function embeddingRoadmap(): array
    {
        return [
            'phase_1' => 'keyword search',
            'phase_2' => 'OpenAI embeddings',
            'phase_3' => 'Pinecone / FAISS / PGVector / Milvus',
            'phase_4' => 'hybrid semantic search + pharmacy RAG'
        ];
    }
}
