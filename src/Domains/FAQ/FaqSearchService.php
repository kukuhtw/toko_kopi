<?php

declare(strict_types=1);

namespace KopiBot\Domains\FAQ;

class FaqSearchService
{
    public function __construct(
        private FaqRepository $repository = new FaqRepository()
    ) {}

    public function search(int $tenantId, ?int $branchId, string $question, int $limit = 5): array
    {
        $results = $this->repository->searchKeyword($tenantId, $branchId, $question, $limit);

        return [
            'success' => true,
            'question' => $question,
            'total' => count($results),
            'data' => $results,
        ];
    }
}
