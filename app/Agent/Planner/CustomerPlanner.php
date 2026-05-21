<?php

declare(strict_types=1);

namespace App\Agent\Planner;

final class CustomerPlanner
{
    /**
     * Produces a minimal deterministic plan that a future LLM planner can enrich.
     *
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $toolDescriptions
     * @return array<int, array<string, mixed>>
     */
    public function buildPlan(array $context, array $toolDescriptions): array
    {
        $intent = (string)($context['intent'] ?? '');
        $message = mb_strtolower(trim((string)($context['message'] ?? '')), 'UTF-8');

        if ($intent === 'lihat_cart') {
            return [[
                'goal' => 'Read current cart state',
                'tool' => 'get_cart_snapshot',
                'input' => [],
            ]];
        }

        if ($intent === 'tanya_promo') {
            return [[
                'goal' => 'Fetch active branch promos',
                'tool' => 'get_active_promos',
                'input' => [],
            ]];
        }

        if ($intent === 'pakai_promo') {
            return [[
                'goal' => 'Apply explicit promo code to the current cart',
                'tool' => 'apply_promo',
                'input' => [],
            ]];
        }

        if ($intent === 'tambah_item' && preg_match('/\b(favorit|langganan|biasanya|seperti biasa|kayak kemarin|seperti kemarin)\b/u', $message)) {
            return [[
                'goal' => 'Add a familiar customer-preferred item to the cart',
                'tool' => 'add_to_cart',
                'input' => ['use_customer_history' => true],
            ]];
        }

        if (preg_match('/\b(alamat|lokasi|dimana|di mana|where|jam buka|jam operasional|buka jam|tutup jam|kontak|telepon|nomor cabang|cabang mana|branch mana|cabang ini|which branch|what branch)\b/u', $message)) {
            return [[
                'goal' => 'Fetch branch contact and operating information',
                'tool' => 'get_branch_info',
                'input' => [],
            ]];
        }

        if (preg_match('/\b(rekomendasi|sarankan|cocok|budget|manis|pahit|creamy|ringan|yang dingin|yang panas|mirip|favorit|langganan|biasanya|seperti biasa|kayak kemarin|seperti kemarin)\b/u', $message)) {
            return [[
                'goal' => 'Recommend menu based on budget and flavor preferences',
                'tool' => 'recommend_menu',
                'input' => [],
            ]];
        }

        return [[
            'goal' => 'Fetch available menu context for advisory response',
            'tool' => 'get_branch_menu',
            'input' => [],
        ]];
    }
}
