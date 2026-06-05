<?php

declare(strict_types=1);

namespace KopiBot\Domains\Chatbot;

class CartIntentParser
{
    public function parseAddItem(string $message): ?array
    {
        $message = strtolower(trim($message));
        $message = preg_replace('/\s+/', ' ', $message) ?: $message;

        if (!preg_match('/^(pesan|order|beli|tambah)\s+(.+?)(?:\s+(\d+))?$/i', $message, $matches)) {
            return null;
        }

        $productName = trim($matches[2] ?? '');
        $qty = isset($matches[3]) ? (int) $matches[3] : 1;

        if ($productName === '' || $qty <= 0) {
            return null;
        }

        return [
            'product_name' => $productName,
            'qty' => $qty,
        ];
    }

    public function isCheckoutIntent(string $message): bool
    {
        $message = strtolower(trim($message));

        return in_array($message, [
            'checkout',
            'bayar',
            'lanjut bayar',
            'buat order',
            'proses order',
        ], true);
    }
}
