<?php

declare(strict_types=1);

namespace KopiBot\Domains\Chatbot;

class IntentDetector
{
    public function detect(string $message): string
    {
        $message = strtolower(trim($message));

        if ($message === '') {
            return IntentType::UNKNOWN;
        }

        if ($this->containsAny($message, ['menu', 'daftar menu', 'katalog', 'produk apa'])) {
            return IntentType::SHOW_MENU;
        }

        if ($this->containsAny($message, ['pesan', 'order', 'beli', 'checkout'])) {
            return IntentType::CREATE_ORDER;
        }

        if ($this->containsAny($message, ['promo', 'voucher', 'diskon', 'kupon'])) {
            return IntentType::APPLY_PROMO;
        }

        if ($this->containsAny($message, ['status order', 'cek order', 'pesanan saya', 'order saya'])) {
            return IntentType::CHECK_ORDER_STATUS;
        }

        if ($this->containsAny($message, ['cs', 'admin', 'manusia', 'operator', 'komplain', 'keluhan'])) {
            return IntentType::TALK_TO_HUMAN;
        }

        if ($this->containsAny($message, ['ada', 'cari', 'mau', 'ingin', 'butuh'])) {
            return IntentType::PRODUCT_SEARCH;
        }

        return IntentType::ASK_FAQ;
    }

    private function containsAny(string $message, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
