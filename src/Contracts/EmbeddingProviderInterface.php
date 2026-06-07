<?php

declare(strict_types=1);

namespace KopiBot\Contracts;

interface EmbeddingProviderInterface
{
    public function getName(): string;

    public function getModel(): string;

    public function getDimensions(): int;

    public function isAvailable(): bool;

    /**
     * @return list<float>
     */
    public function embed(string $text): array;

    /**
     * @param list<string> $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array;
}
