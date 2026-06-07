<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

use KopiBot\Contracts\SkillInterface;
use KopiBot\Core\LlmProviderRegistry;
use KopiBot\Domains\Chatbot\IntentType;

class LLMSkill implements SkillInterface
{
    public function canHandle(string $intent): bool
    {
        return $intent === IntentType::UNKNOWN || $intent === 'llm_fallback';
    }

    public function handle(array $context): array
    {
        $provider = LlmProviderRegistry::preferred((string)($context['llm_provider'] ?? ''));
        if ($provider === null) {
            return [
                'success' => false,
                'reply' => 'Maaf, saya belum memahami pesan Anda. Anda bisa bertanya tentang menu, promo, jam buka, atau cara order.',
                'state' => 'llm_unavailable',
            ];
        }

        $message = trim((string)($context['message'] ?? ''));
        if ($message === '') {
            return [
                'success' => false,
                'reply' => 'Silakan tulis pertanyaan Anda.',
                'state' => 'empty_message',
            ];
        }

        $systemPrompt = $this->buildSystemPrompt($context);

        try {
            $reply = trim((string)$provider->chat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $message],
            ], [
                'max_tokens' => 500,
                'temperature' => 0.4,
            ]));
        } catch (\Throwable $e) {
            error_log('[LLMSkill] ' . $e->getMessage());
            $reply = '';
        }

        if ($reply === '') {
            $reply = 'Maaf, saya belum bisa menjawab pertanyaan itu saat ini. Anda bisa bertanya tentang menu, promo, order, atau checkout.';
        }

        return [
            'success' => true,
            'reply' => $reply,
            'state' => 'llm_answered',
            'provider' => $provider->getName(),
            'model' => $provider->getModel(),
            'usage' => $provider->getLastUsage(),
        ];
    }

    private function buildSystemPrompt(array $context): string
    {
        $branchId = (int)($context['branch_id'] ?? 0);
        $channel = (string)($context['channel'] ?? 'web');

        return implode("\n", [
            'Anda adalah asisten chatbot toko/kafe yang membantu pelanggan secara ramah, singkat, dan jelas.',
            'Jawab hanya untuk konteks layanan toko: menu, produk, promo, cara order, checkout, pembayaran, cabang, jam buka, dan bantuan pelanggan.',
            'Jika pertanyaan di luar konteks toko, arahkan kembali dengan sopan ke topik menu, promo, atau order.',
            'Jangan mengarang harga, stok, promo, atau kebijakan. Jika data tidak tersedia, katakan bahwa informasi belum tersedia.',
            'Gunakan Bahasa Indonesia kecuali pelanggan memakai Bahasa Inggris.',
            'Konteks teknis: branch_id=' . $branchId . ', channel=' . $channel . '.',
        ]);
    }
}
