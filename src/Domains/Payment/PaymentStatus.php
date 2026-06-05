<?php

declare(strict_types=1);

namespace KopiBot\Domains\Payment;

class PaymentStatus
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';
    public const CANCELLED = 'cancelled';
}
