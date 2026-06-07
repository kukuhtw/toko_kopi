<?php

declare(strict_types=1);

namespace KopiBot\Contracts;

interface VectorStoreInterface
{
    public function getName(): string;

    public function isAvailable(): bool;

    public function upsert(string $namespace, string $id, array $vector, array $metadata = []): bool;

    public function delete(string $namespace, string $id): bool;

    public function fetch(string $namespace, string $id): ?array;

    public function similaritySearch(
        string $namespace,
        array $vector,
        int $topK = 5,
        array $filter = []
    ): array;
}
