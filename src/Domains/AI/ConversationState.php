<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

class ConversationState
{
    public const IDLE = 'idle';
    public const WAITING_FOR_QTY = 'waiting_for_qty';
    public const WAITING_FOR_PRODUCT_OPTION = 'waiting_for_product_option';
    public const WAITING_FOR_CHECKOUT_CONFIRMATION = 'waiting_for_checkout_confirmation';
}
