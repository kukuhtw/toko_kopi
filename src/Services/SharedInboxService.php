<?php

declare(strict_types=1);

namespace KopiBot\Services;

use KopiBot\Core\DatabaseConnection;
use KopiBot\Domains\Branch\BranchRepository;
use PDO;

class SharedInboxService
{
    private BranchRepository $branchRepository;
    private PDO $db;

    public function __construct(?BranchRepository $branchRepository = null, ?PDO $db = null)
    {
        $this->branchRepository = $branchRepository ?? new BranchRepository();
        $this->db = $db ?? DatabaseConnection::getInstance();
    }

    public function resolveBranch(
        string $channel,
        int $transportBranchId,
        string $customerIdentifier,
        string $message,
        string $enabledSettingKey = 'whatsapp_shared_inbox_enabled'
    ): array {
        if (!$this->isEnabled($transportBranchId, $enabledSettingKey)) {
            return ['handled' => false, 'branch_id' => $transportBranchId];
        }

        $branches = $this->branchRepository->getActive();
        if (count($branches) <= 1) {
            return ['handled' => false, 'branch_id' => $transportBranchId];
        }

        $customer = $this->findOrCreateCustomer($channel, $customerIdentifier);
        $sessionKey = hash('sha256', "shared-inbox:{$channel}:{$transportBranchId}:{$customerIdentifier}");
        $conversation = $this->getOrCreateConversation($transportBranchId, (int)$customer['id'], $channel, $sessionKey);
        $context = $this->getConversationContext((int)$conversation['id']);

        if ($this->wantsBranchSwitch($message)) {
            $this->updateConversationState((int)$conversation['id'], 'awaiting_branch_selection', []);
            return ['handled' => true, 'reply_message' => $this->buildBranchPrompt($branches, true)];
        }

        $selectedBranchId = (int)($context['selected_branch_id'] ?? 0);
        if ($selectedBranchId > 0 && $this->branchExists($branches, $selectedBranchId)) {
            return ['handled' => false, 'branch_id' => $selectedBranchId];
        }

        $matchedBranch = $this->matchBranchChoice($branches, $message);
        if ($matchedBranch !== null) {
            $newContext = [
                'selected_branch_id'   => (int)$matchedBranch['id'],
                'selected_branch_name' => (string)$matchedBranch['name'],
            ];
            $this->updateConversationState((int)$conversation['id'], 'branch_selected', $newContext);

            return [
                'handled'       => true,
                'reply_message' => 'Cabang *' . $matchedBranch['name'] . '* dipilih.'
                    . "\nSilakan lanjut kirim pesanan atau pertanyaan kamu."
                    . "\nKalau ingin pindah cabang, ketik *ganti cabang*.",
            ];
        }

        $this->updateConversationState((int)$conversation['id'], 'awaiting_branch_selection', $context);
        return ['handled' => true, 'reply_message' => $this->buildBranchPrompt($branches, false)];
    }

    private function isEnabled(int $branchId, string $enabledSettingKey): bool
    {
        return $this->branchRepository->getSetting($branchId, $enabledSettingKey, '0') === '1';
    }

    private function findOrCreateCustomer(string $channel, string $identifier): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM customers WHERE channel = ? AND identifier = ? LIMIT 1'
        );
        $stmt->execute([$channel, $identifier]);
        $existing = $stmt->fetch();
        if ($existing) {
            return $existing;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO customers (channel, identifier) VALUES (?, ?)'
        );
        $stmt->execute([$channel, $identifier]);
        $customerId = (int)$this->db->lastInsertId();

        $profileStmt = $this->db->prepare(
            'INSERT INTO customer_profiles (customer_id) VALUES (?)'
        );
        $profileStmt->execute([$customerId]);

        $stmt = $this->db->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$customerId]);
        return $stmt->fetch() ?: ['id' => $customerId, 'channel' => $channel, 'identifier' => $identifier];
    }

    private function getOrCreateConversation(int $branchId, int $customerId, string $channel, string $sessionKey): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM conversations WHERE session_key = ? AND ended_at IS NULL LIMIT 1'
        );
        $stmt->execute([$sessionKey]);
        $conversation = $stmt->fetch();

        if (!$conversation) {
            $insert = $this->db->prepare(
                'INSERT INTO conversations (branch_id, customer_id, channel, session_key, state)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $insert->execute([$branchId, $customerId, $channel, $sessionKey, 'idle']);
            $conversationId = (int)$this->db->lastInsertId();

            $stmt = $this->db->prepare('SELECT * FROM conversations WHERE id = ? LIMIT 1');
            $stmt->execute([$conversationId]);
            return $stmt->fetch() ?: ['id' => $conversationId];
        }

        if ((int)($conversation['customer_id'] ?? 0) !== $customerId && $customerId > 0) {
            $update = $this->db->prepare(
                'UPDATE conversations SET customer_id = ?, last_activity = NOW() WHERE id = ?'
            );
            $update->execute([$customerId, (int)$conversation['id']]);

            $stmt = $this->db->prepare('SELECT * FROM conversations WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$conversation['id']]);
            $conversation = $stmt->fetch() ?: $conversation;
        }

        return $conversation;
    }

    private function getConversationContext(int $conversationId): array
    {
        $stmt = $this->db->prepare('SELECT context_data FROM conversations WHERE id = ? LIMIT 1');
        $stmt->execute([$conversationId]);
        $contextData = (string)($stmt->fetchColumn() ?: '');
        if ($contextData === '') {
            return [];
        }

        $context = json_decode($contextData, true);
        return is_array($context) ? $context : [];
    }

    private function updateConversationState(int $conversationId, string $state, array $contextData = []): void
    {
        $stmt = $this->db->prepare(
            'UPDATE conversations
             SET state = ?, context_data = ?, last_activity = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $state,
            $contextData !== [] ? json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $conversationId,
        ]);
    }

    private function branchExists(array $branches, int $branchId): bool
    {
        foreach ($branches as $branch) {
            if ((int)($branch['id'] ?? 0) === $branchId) {
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
            $index = (int)$match[1] - 1;
            if (isset($branches[$index])) {
                return $branches[$index];
            }
        }

        $normalizedMessage = $this->normalize($message);
        foreach ($branches as $branch) {
            $name = $this->normalize((string)($branch['name'] ?? ''));
            $slug = $this->normalize((string)($branch['slug'] ?? ''));
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
