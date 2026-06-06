<?php

declare(strict_types=1);

namespace KopiBot\Contracts;

interface LlmProviderInterface
{
    public function getName(): string;

    public function chat(array $messages, array $options = []): string;

    public function estimateCost(int $promptTokens, int $completionTokens): float;

    public function isAvailable(): bool;
}
