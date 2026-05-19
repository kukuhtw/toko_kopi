<?php

declare(strict_types=1);

namespace App\Agent;

interface ToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * Read-only tools can be called freely by the advisory agent.
     * Mutating tools should pass PolicyEngine checks first.
     */
    public function isMutating(): bool;

    /**
     * JSON-schema-like shape used later for tool prompting and validation.
     *
     * @return array<string, mixed>
     */
    public function getInputSchema(): array;

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function execute(array $input, array $context = []): array;
}
