<?php

declare(strict_types=1);

namespace KopiBot\Domains\FAQ;

class FaqService
{
    public function __construct(
        private FaqRepository $repository = new FaqRepository()
    ) {}

    public function answer(int $tenantId, ?int $branchId, string $question, ?int $customerId = null): array
    {
        $results = $this->repository->searchKeyword($tenantId, $branchId, $question, 1);

        if (empty($results)) {
            $unansweredId = $this->repository->logUnanswered($tenantId, $branchId, $customerId, $question);

            return [
                'success' => false,
                'answered' => false,
                'message' => 'Maaf, saya belum menemukan jawaban untuk pertanyaan tersebut.',
                'unanswered_id' => $unansweredId,
            ];
        }

        $faq = $results[0];

        return [
            'success' => true,
            'answered' => true,
            'faq_id' => (int) $faq['id'],
            'question' => $faq['question'],
            'answer' => $faq['answer'],
        ];
    }
}
