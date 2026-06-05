<?php

declare(strict_types=1);

namespace KopiBot\Domains\Loyalty;

class LoyaltyRule
{
    public const POINT_PER_RUPIAH = 1000;

    public static function calculateEarnPoint(float $grandTotal): int
    {
        return (int) floor($grandTotal / self::POINT_PER_RUPIAH);
    }
}
