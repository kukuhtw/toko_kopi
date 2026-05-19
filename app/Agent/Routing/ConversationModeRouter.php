<?php

declare(strict_types=1);

namespace App\Agent\Routing;

final class ConversationModeRouter
{
    /**
     * Route incoming customer requests to the safest processing mode.
     *
     * @param array<string, mixed> $context
     */
    public function route(array $context): string
    {
        $intent = (string)($context['intent'] ?? '');
        $message = mb_strtolower(trim((string)($context['message'] ?? '')), 'UTF-8');
        $conversationState = (string)($context['conversation']['state'] ?? 'idle');

        // When the legacy commerce flow is waiting for a follow-up like
        // size/topping/notes, always let ChatbotEngine finish that state.
        if ($conversationState !== '' && $conversationState !== 'idle') {
            return 'transactional';
        }

        if (in_array($intent, [
            'ubah_item', 'hapus_item', 'clear_cart',
            'checkout', 'konfirmasi_order',
            'isi_nama', 'isi_email', 'isi_alamat', 'isi_kode_pos', 'isi_wa',
            'tanya_menu', 'tanya_harga', 'small_talk',
        ], true)) {
            return 'transactional';
        }

        if ($intent === 'pakai_promo') {
            return 'advisory';
        }

        if (
            $intent === 'tambah_item'
            && $conversationState === 'idle'
            && preg_match('/\b(favorit|langganan|biasanya|seperti biasa|kayak kemarin|seperti kemarin)\b/u', $message)
        ) {
            return 'advisory';
        }

        if (preg_match('/\b(rekomendasi|sarankan|cocok|budget|mirip|beda apa|yang manis|yang pahit|yang ringan|favorit|langganan|biasanya|seperti biasa|kayak kemarin|seperti kemarin)\b/u', $message)) {
            return 'advisory';
        }

        if (preg_match('/\b(alamat|lokasi|dimana|di mana|where|jam buka|jam operasional|buka jam|tutup jam|kontak|telepon|nomor cabang)\b/u', $message)) {
            return 'advisory';
        }

        if (in_array($intent, ['tanya_promo', 'lihat_cart', 'out_of_scope'], true)) {
            return 'advisory';
        }

        return 'transactional';
    }
}
