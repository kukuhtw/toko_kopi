<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\ToolInterface;
use App\Models\PromoModel;

final class GetActivePromosTool implements ToolInterface
{
    private PromoModel $promoModel;

    public function __construct()
    {
        $this->promoModel = new PromoModel();
    }

    public function getName(): string
    {
        return 'get_active_promos';
    }

    public function getDescription(): string
    {
        return 'Get currently active promos for the current branch.';
    }

    public function isMutating(): bool
    {
        return false;
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
        $branchId = (int)($context['branch_id'] ?? 0);
        return [
            'promos' => $branchId > 0 ? $this->promoModel->getActiveForBranch($branchId, (string)($context['now_local'] ?? '')) : [],
        ];
    }
}
