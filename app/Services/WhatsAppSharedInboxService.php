<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{BranchModel, ConversationModel, CustomerModel};

class WhatsAppSharedInboxService
{
    private BranchModel $branchModel;
    private CustomerModel $customerModel;
    private ConversationModel $conversationModel;

    public function __construct()
    {
        $this->branchModel = new BranchModel();
        $this->customerModel = new CustomerModel();
        $this->conversationModel = new ConversationModel();
    }

    public function resolveBranch(
        string $channel,
        int $transportBranchId,
        string $customerIdentifier,
        string $message,
        string $enabledSettingKey = 'whatsapp_shared_inbox_enabled'
    ): array
    {
        if (!$this->isEnabled($transportBranchId, $enabledSettingKey)) {
            return ['handled' => false, 'branch_id' => $transportBranchId];
        }

        $branches = $this->branchModel->getActive();
        if (count($branches) <= 1) {
            return ['handled' => false, 'branch_id' => $transportBranchId];
        }

        $customer = $this->customerModel->findOrCreate($channel, $customerIdentifier);
        $sessionKey = hash('sha256', "shared-inbox:{$channel}:{$transportBranchId}:{$customerIdentifier}");
        $conversation = $this->conversationModel->getOrCreate($transportBranchId, (int) $customer['id'], $channel, $sessionKey);
        $context = $this->conversationModel->getContext((int) $conversation['id']);

        if ($this->wantsBranchSwitch($message)) {
            $this->conversationModel->updateState((int) $conversation['id'], 'awaiting_branch_selection', []);
            return ['handled' => true, 'reply_message' => $this->buildBranchPrompt($branches, true)];
        }

        $selectedBranchId = (int) ($context['selected_branch_id'] ?? 0);
        if ($selectedBranchId > 0 && $this->branchExists($branches, $selectedBranchId)) {
            return ['handled' => false, 'branch_id' => $selectedBranchId];
        }

        $matchedBranch = $this->matchBranchChoice($branches, $message);
        if ($matchedBranch !== null) {
            $newContext = [
                'selected_branch_id'   => (int) $matchedBranch['id'],
                'selected_branch_name' => (string) $matchedBranch['name'],
            ];
            $this->conversationModel->updateState((int) $conversation['id'], 'branch_selected', $newContext);

            return [
                'handled'       => true,
                'reply_message' => 'Cabang *' . $matchedBranch['name'] . '* dipilih.'
                    . "\nSilakan lanjut kirim pesanan atau pertanyaan kamu."
                    . "\nKalau ingin pindah cabang, ketik *ganti cabang*.",
            ];
        }

        $this->conversationModel->updateState((int) $conversation['id'], 'awaiting_branch_selection', $context);
        return ['handled' => true, 'reply_message' => $this->buildBranchPrompt($branches, false)];
    }

    private function isEnabled(int $branchId, string $enabledSettingKey): bool
    {
        return $this->branchModel->getSetting($branchId, $enabledSettingKey, '0') === '1';
    }

    private function branchExists(array $branches, int $branchId): bool
    {
        foreach ($branches as $branch) {
            if ((int) ($branch['id'] ?? 0) === $branchId) {
                return true;
            }
        }

        return false;
    }

    private function wantsBranchSwitch(string $message): bool
    {
        $normalized = $this->normalize($message);
        foreach ([
            'ganti cabang',
            'pilih cabang',
            'pindah cabang',
            'ubah cabang',
            'switch branch',
            'change branch',
        ] as $needle) {
            if (str_contains($normalized, $this->normalize($needle))) {
                return true;
            }
        }

        return false;
    }

    private function matchBranchChoice(array $branches, string $message): ?array
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\D*(\d{1,2})\D*$/', $trimmed, $match) === 1) {
            $index = (int) $match[1] - 1;
            if (isset($branches[$index])) {
                return $branches[$index];
            }
        }

        $normalizedMessage = $this->normalize($message);
        foreach ($branches as $branch) {
            $name = $this->normalize((string) ($branch['name'] ?? ''));
            $slug = $this->normalize((string) ($branch['slug'] ?? ''));
            if ($name !== '' && str_contains($normalizedMessage, $name)) {
                return $branch;
            }
            if ($slug !== '' && str_contains($normalizedMessage, $slug)) {
                return $branch;
            }
        }

        return null;
    }

    private function buildBranchPrompt(array $branches, bool $isSwitch): string
    {
        $lines = [];
        $lines[] = $isSwitch
            ? 'Siap, kamu mau pindah ke cabang yang mana?'
            : 'Sebelum lanjut, pilih dulu cabang yang ingin kamu tuju:';

        foreach ($branches as $index => $branch) {
            $lines[] = ($index + 1) . '. ' . ($branch['name'] ?? ('Cabang #' . ($branch['id'] ?? '?')));
        }

        $lines[] = 'Balas dengan angka atau nama cabangnya.';
        return implode("\n", $lines);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        return trim($value);
    }
}
