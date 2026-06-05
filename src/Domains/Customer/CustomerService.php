<?php

declare(strict_types=1);

namespace KopiBot\Domains\Customer;

class CustomerService
{
    public function __construct(
        private CustomerRepository $repository = new CustomerRepository()
    ) {}

    public function findOrCreate(CustomerDTO $dto): array
    {
        $existing = null;

        if (!empty($dto->email)) {
            $existing = $this->repository->findByEmail($dto->tenantId, $dto->email);
        }

        if (!$existing && !empty($dto->phone)) {
            $existing = $this->repository->findByPhone($dto->tenantId, $dto->phone);
        }

        if (!$existing && !empty($dto->whatsapp)) {
            $existing = $this->repository->findByPhone($dto->tenantId, $dto->whatsapp);
        }

        if ($existing) {
            $customerId = (int) $existing['id'];
            $this->repository->updateProfile($customerId, $dto);

            return [
                'success' => true,
                'customer_id' => $customerId,
                'is_new' => false,
            ];
        }

        $customerId = $this->repository->create($dto);

        return [
            'success' => true,
            'customer_id' => $customerId,
            'is_new' => true,
        ];
    }
}
