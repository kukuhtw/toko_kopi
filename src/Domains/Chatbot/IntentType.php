<?php

declare(strict_types=1);

namespace KopiBot\Domains\Chatbot;

class IntentType
{
    public const SHOW_MENU = 'show_menu';
    public const PRODUCT_SEARCH = 'product_search';
    public const ASK_FAQ = 'ask_faq';
    public const APPLY_PROMO = 'apply_promo';
    public const CREATE_ORDER = 'create_order';
    public const CHECK_ORDER_STATUS = 'check_order_status';
    public const TALK_TO_HUMAN = 'talk_to_human';
    public const UNKNOWN = 'unknown';
}
