<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

use KopiBot\Contracts\SkillInterface;
use KopiBot\Core\LlmProviderRegistry;
use KopiBot\Domains\Knowledge\KnowledgeRepository;

class RAGSkill implements SkillInterface
{
    public function __construct(
        private KnowledgeRepository $knowledgeRepository = new KnowledgeRepository()
    ) {}

    public function canHandle(string $intent): bool
    {
        return in_array($intent, ['ask_faq', 'rag_query', 'knowledge_query'], true);
    }

    public function handle(array $context): array
    {
        $tenantId = (int)($context['tenant_id'] ?? 1);
        $branchId = (int)($context['branch_id'] ?? 0);
        $message = trim((string)($context['message'] ?? ''));

        if ($message === '') {
            return [
                'success' => false,
                'reply' => 'Silakan tulis pertanyaan Anda.',
                'state' => 'empty_message',
            ];
        }

        $sources = $this->knowledgeRepository->search($tenantId, $branchId > 0 ? $branchId : null, $message, 5);
        if ($sources === []) {
            return [
                'success' => false,
                'reply' => '',
                'state' => 'knowledge_not_found',
                'sources' => [],
            ];
        }

        $provider = LlmProviderRegistry::preferred((string)($context['llm_provider'] ?? ''));
        if ($provider === null) {
            return [
                'success' => true,
                'reply' => $this->answerWithoutLlm($sources),
                'state' => 'rag_answered_without_llm',
                'sources' => $sources,
            ];
        }

        $systemPrompt = $this->buildSystemPrompt($sources);

        try {
            $reply = trim((string)$provider->chat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $message],
            ], [
                'max_tokens' => 700,
                'temperature' => 0.2,
            ]));
        } catch (\Throwable $e) {
            error_log('[RAGSkill] ' . $e->getMessage());
            $reply = '';
        }

        if ($reply === '') {
            $reply = $this->answerWithoutLlm($sources);
        }

        return [
            'success' => true,
            'reply' => $reply,
            'state' => 'rag_answered',
            'sources' => $sources,
            'provider' => $provider->getName(),
            'model' => $provider->getModel(),
            'usage' => $provider->getLastUsage(),
        ];
    }

    private function buildSystemPrompt(array $sources): string
    {
        $contextBlocks = [];
        foreach ($sources as $index => $source) {
            $contextBlocks[] = sprintf(
                "[%d] Source: %s\nTitle: %s\nContent:\n%s",
                $index + 1,
                (string)($source['source'] ?? 'knowledge'),
                (string)($source['title'] ?? ''),
                (string)($source['content'] ?? '')
            );
        }

        return implode("\n\n", [
            'Anda adalah asisten toko/kafe yang menjawab pertanyaan pelanggan berdasarkan konteks knowledge base di bawah.',
            'Gunakan hanya informasi dari konteks. Jangan mengarang harga, stok, promo, jam operasional, alamat, atau kebijakan.',
            'Jika konteks tidak cukup, jawab bahwa informasi belum tersedia dan arahkan pelanggan menghubungi admin/CS.',
            'Jawab ringkas, ramah, dan praktis dalam Bahasa Indonesia kecuali pelanggan memakai Bahasa Inggris.',
            'KONTEKS KNOWLEDGE BASE:',
            implode("\n\n", $contextBlocks),
        ]);
    }

    private function answerWithoutLlm(array $sources): string
    {
        $first = $sources[0] ?? [];
        $title = trim((string)($first['title'] ?? ''));
        $content = trim((string)($first['content'] ?? ''));

        if ($content === '') {
            return 'Informasi terkait ditemukan, tetapi detail jawabannya belum tersedia lengkap.';
        }

        $prefix = $title !== '' ? $title . "\n" : '';
        return $prefix . mb_substr($content, 0, 900);
    }
}
