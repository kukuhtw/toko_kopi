<?php

declare(strict_types=1);

namespace KopiBot\Domains\CRM;

class CrmService
{
    public function __construct(
        private CrmRepository $repository = new CrmRepository()
    ) {}

    public function log(CrmEventDTO $dto): array
    {
        $eventId = $this->repository->logEvent($dto);

        return [
            'success' => true,
            'event_id' => $eventId,
        ];
    }

    public function history(int $tenantId, int $customerId, int $limit = 50): array
    {
        return [
            'success' => true,
            'customer_id' => $customerId,
            'data' => $this->repository->findByCustomerId($tenantId, $customerId, $limit),
        ];
    }
}
