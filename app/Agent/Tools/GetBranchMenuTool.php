<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\ToolInterface;
use App\Models\MenuModel;

final class GetBranchMenuTool implements ToolInterface
{
    private MenuModel $menuModel;

    public function __construct()
    {
        $this->menuModel = new MenuModel();
    }

    public function getName(): string
    {
        return 'get_branch_menu';
    }

    public function getDescription(): string
    {
        return 'Get active menu items for the current branch.';
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
            'items' => $branchId > 0 ? $this->menuModel->getMenuForBranch($branchId) : [],
        ];
    }
}
