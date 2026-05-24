<?php

declare(strict_types=1);

use App\Skills\SkillInterface;

final class FaqSkill implements SkillInterface
{
    private FaqRagResponder $rag;
    private FaqRepository $repo;

    public function __construct(?FaqRagResponder $rag = null, ?FaqRepository $repo = null)
    {
        $this->rag = $rag ?? new FaqRagResponder();
        $this->repo = $repo ?? new FaqRepository();
    }

    public function canHandle(string $intent): bool
    {
        return $intent === 'faq_customer';
    }

    public function handle(array $context): array
    {
        $branchId = (int)($context['branch_id'] ?? 0);
        $language = (string)($context['language'] ?? 'id');
        $message = trim((string)($context['message'] ?? ''));
        $convContext = (array)($context['conv_context'] ?? []);

        $resolved = $this->rag->answer($message, $branchId, $language);
        if ($resolved === null) {
            $this->repo->logQuery(
                $branchId,
                !empty($context['customer']['id']) ? (int)$context['customer']['id'] : null,
                !empty($context['conversation']['id']) ? (int)$context['conversation']['id'] : null,
                $message,
                null,
                null,
                null
            );
            $reply = $language === 'en'
                ? "Sorry, I couldn't find a matching FAQ yet. Please rephrase your question or contact the store staff."
                : "Maaf, saya belum menemukan FAQ yang cocok. Coba ubah pertanyaannya atau hubungi staf toko.";
            return [
                'reply' => $reply,
                'state' => 'idle',
                'action_result' => null,
                'conv_context' => $convContext,
            ];
        }

        $matches = $resolved['matches'] ?? [];
        $top = $matches[0] ?? null;
        $this->repo->logQuery(
            $branchId,
            !empty($context['customer']['id']) ? (int)$context['customer']['id'] : null,
            !empty($context['conversation']['id']) ? (int)$context['conversation']['id'] : null,
            $message,
            is_array($top) ? (int)($top['id'] ?? 0) : null,
            is_array($top) ? (float)($top['_score'] ?? 0.0) : null,
            is_array($top) ? (string)($top['scope'] ?? '') : null
        );
        $convContext['last_topic'] = 'faq';
        $convContext['last_faq_ids'] = array_values(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            is_array($matches) ? $matches : []
        ));

        return [
            'reply' => (string)$resolved['reply'],
            'state' => 'idle',
            'action_result' => [
                'faq_match_count' => count($matches),
            ],
            'conv_context' => $convContext,
        ];
    }
}
