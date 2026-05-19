<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\ToolInterface;
use App\Models\BranchModel;

final class GetBranchInfoTool implements ToolInterface
{
    private BranchModel $branchModel;

    public function __construct()
    {
        $this->branchModel = new BranchModel();
    }

    public function getName(): string
    {
        return 'get_branch_info';
    }

    public function getDescription(): string
    {
        return 'Get contact details, address, operating hours, and branch profile information.';
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
        if ($branchId <= 0) {
            return ['branch' => null, 'settings' => []];
        }

        $branch = $this->branchModel->find($branchId);
        $settings = $this->branchModel->getAllSettings($branchId);

        return [
            'branch' => $branch ?: null,
            'settings' => [
                'description_id' => (string)($settings['description_id'] ?? ''),
                'description_en' => (string)($settings['description_en'] ?? ''),
                'hours_id' => (string)($settings['hours_id'] ?? ''),
                'hours_en' => (string)($settings['hours_en'] ?? ''),
                'name_en' => (string)($settings['name_en'] ?? ''),
            ],
        ];
    }
}
