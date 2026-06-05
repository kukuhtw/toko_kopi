<?php

declare(strict_types=1);

namespace KopiBot\Domains\Loyalty;

class LoyaltyTransactionType
{
    public const EARN = 'earn';
    public const REDEEM = 'redeem';
    public const ADJUSTMENT = 'adjustment';
    public const EXPIRED = 'expired';
}
