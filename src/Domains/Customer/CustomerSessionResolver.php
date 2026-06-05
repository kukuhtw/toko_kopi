<?php

declare(strict_types=1);

namespace KopiBot\Domains\Customer;

class CustomerSessionResolver
{
    public function __construct(
        private CustomerService $customerService = new CustomerService()
    ) {}

    public function resolveFromWhatsApp(int $tenantId, int $branchId, string $phone, ?string $name = null): array
    {
        return $this->customerService->findOrCreate(new CustomerDTO(
            tenantId: $tenantId,
            branchId: $branchId,
            name: $name ?: 'WhatsApp Customer ' . $phone,
            phone: $phone,
            whatsapp: $phone,
            source: 'whatsapp'
        ));
    }
}
