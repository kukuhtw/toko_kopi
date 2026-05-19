<?php

declare(strict_types=1);

namespace App\Agent\Policy;

use App\Agent\ToolInterface;

final class PolicyEngine
{
    /**
     * Guardrails for customer-facing agent actions.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $input
     */
    public function evaluate(ToolInterface $tool, array $context, array $input = []): PolicyDecision
    {
        if (!$tool->isMutating()) {
            return PolicyDecision::allow();
        }

        $intent = (string)($context['intent'] ?? '');
        $message = mb_strtolower(trim((string)($context['message'] ?? '')), 'UTF-8');
        $conversationState = (string)($context['conversation']['state'] ?? 'idle');

        if ($conversationState === 'awaiting_confirmation' && preg_match('/\b(tidak|bukan|jangan|ubah|ganti|hapus)\b/u', $message)) {
            return PolicyDecision::deny(
                'Customer tampak sedang mengoreksi pesanan. Tahan mutasi otomatis dan minta klarifikasi.',
                'ask_clarification'
            );
        }

        if ($tool->getName() === 'begin_checkout' && !in_array($intent, ['checkout', 'konfirmasi_order'], true)) {
            return PolicyDecision::deny(
                'Checkout hanya boleh dimulai ketika customer memang berniat checkout.',
                'ask_clarification'
            );
        }

        if ($tool->getName() === 'add_to_cart') {
            if ($conversationState !== 'idle') {
                return PolicyDecision::deny(
                    'Percakapan masih berada di state interaktif lama. Serahkan penambahan item ke flow deterministic.',
                    'chatbot_engine'
                );
            }

            if ($intent !== 'tambah_item') {
                return PolicyDecision::deny(
                    'Tambah item hanya boleh jalan saat intent customer memang menambah item.',
                    'ask_clarification'
                );
            }
        }

        if ($tool->getName() === 'apply_promo' && $intent !== 'pakai_promo') {
            return PolicyDecision::deny(
                'Promo hanya boleh dipakai saat customer memang sedang mencoba memakai kode promo.',
                'ask_clarification'
            );
        }

        return PolicyDecision::allow();
    }
}
