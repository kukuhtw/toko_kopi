<?php

declare(strict_types=1);

namespace KopiBot\Domains\Promo;

class PromoValidator
{
    public function validate(array $promo, PromoDTO $dto): array
    {
        if ((int) $promo['is_active'] !== 1) {
            return [false, 'Promo is inactive'];
        }

        if (!empty($promo['branch_id']) && (int) $promo['branch_id'] !== $dto->branchId) {
            return [false, 'Promo is not available for this branch'];
        }

        if (!empty($promo['start_date']) && strtotime((string) $promo['start_date']) > time()) {
            return [false, 'Promo has not started'];
        }

        if (!empty($promo['end_date']) && strtotime((string) $promo['end_date']) < time()) {
            return [false, 'Promo has expired'];
        }

        if ($dto->subtotal < (float) $promo['minimum_order']) {
            return [false, 'Minimum order has not been reached'];
        }

        return [true, ''];
    }
}
