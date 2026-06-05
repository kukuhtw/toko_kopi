<?php

declare(strict_types=1);

namespace KopiBot\Domains\Order;

class OrderStatus
{
    public const DRAFT = 'draft';
    public const WAITING_PAYMENT = 'waiting_payment';
    public const PAID = 'paid';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
}
