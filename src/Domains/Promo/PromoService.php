<?php

declare(strict_types=1);

namespace KopiBot\Domains\Promo;

class PromoService
{
    public function __construct(
        private PromoRepository $repository = new PromoRepository(),
        private PromoValidator $validator = new PromoValidator()
    ) {}

    public function apply(PromoDTO $dto, bool $logRedemption = false): PromoResult
    {
        $promo = $this->repository->findByCode($dto->tenantId, $dto->promoCode);

        if (!$promo) {
            return new PromoResult(false, 0.0, 'Promo not found');
        }

        [$isValid, $message] = $this->validator->validate($promo, $dto);

        if (!$isValid) {
            return new PromoResult(false, 0.0, $message, $promo);
        }

        $discountAmount = $this->calculateDiscount($promo, $dto->subtotal);

        if ($logRedemption) {
            $this->repository->logRedemption($dto, $promo, $discountAmount);
        }

        return new PromoResult(true, $discountAmount, 'Promo applied', $promo);
    }

    private function calculateDiscount(array $promo, float $subtotal): float
    {
        if ($promo['promo_type'] === PromoType::FIXED) {
            return min((float) $promo['promo_value'], $subtotal);
        }

        if ($promo['promo_type'] === PromoType::PERCENTAGE) {
            $discount = ($subtotal * (float) $promo['promo_value']) / 100;
            $maxDiscount = (float) ($promo['max_discount'] ?? 0);

            return $maxDiscount > 0 ? min($discount, $maxDiscount) : $discount;
        }

        return 0.0;
    }
}
