<?php

declare(strict_types=1);

namespace App\Agent\Memory;

interface MemoryStoreInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMemories(string $scope, string $entityKey, int $limit = 10): array;

    /**
     * @param array<string, mixed> $metadata
     */
    public function remember(string $scope, string $entityKey, string $memoryType, string $content, array $metadata = []): int;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $scope, string $entityKey, string $query, int $limit = 5): array;
}
