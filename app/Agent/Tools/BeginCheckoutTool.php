<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\ToolInterface;

final class BeginCheckoutTool implements ToolInterface
{
    public function getName(): string
    {
        return 'begin_checkout';
    }

    public function getDescription(): string
    {
        return 'Mutation marker to begin the deterministic checkout flow.';
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $input, array $context = []): array
    {
        return [
            'handoff' => 'transactional_checkout',
            'reason' => 'Checkout should be delegated to deterministic commerce core.',
        ];
    }
}
