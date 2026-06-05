<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

class CommerceIntent
{
    public const ADD_TO_CART = 'add_to_cart';
    public const CHECKOUT = 'checkout';
    public const PRODUCT_SEARCH = 'product_search';
    public const ASK_FAQ = 'ask_faq';
    public const UNKNOWN = 'unknown';
}
