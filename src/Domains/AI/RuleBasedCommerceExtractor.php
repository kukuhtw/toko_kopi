<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

class RuleBasedCommerceExtractor implements IntentExtractorInterface
{
    public function extract(string $message): CommerceIntentResult
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $message) ?: $message));

        if ($this->isCheckout($normalized)) {
            return new CommerceIntentResult(CommerceIntent::CHECKOUT);
        }

        $items = $this->extractItems($normalized);

        if (!empty($items)) {
            return new CommerceIntentResult(CommerceIntent::ADD_TO_CART, $items);
        }

        if (str_contains($normalized, 'cari') || str_contains($normalized, 'ada') || str_contains($normalized, 'mau')) {
            return new CommerceIntentResult(CommerceIntent::PRODUCT_SEARCH, [], ['query' => $normalized]);
        }

        if ($normalized !== '') {
            return new CommerceIntentResult(CommerceIntent::ASK_FAQ, [], ['question' => $normalized]);
        }

        return new CommerceIntentResult(CommerceIntent::UNKNOWN);
    }

    private function isCheckout(string $message): bool
    {
        return in_array($message, ['checkout', 'bayar', 'lanjut bayar', 'buat order', 'proses order'], true);
    }

    private function extractItems(string $message): array
    {
        $message = preg_replace('/^(saya\s+mau|mau|tolong|please)\s+/', '', $message) ?: $message;
        $message = preg_replace('/^(pesan|order|beli|tambah)\s+/', '', $message) ?: $message;
        $parts = preg_split('/\s+dan\s+|,/', $message) ?: [];
        $items = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (preg_match('/^(\d+)\s+(.+)$/', $part, $m)) {
                $items[] = ['product_name' => trim($m[2]), 'qty' => (int) $m[1]];
                continue;
            }

            if (preg_match('/^(.+?)\s+(\d+)$/', $part, $m)) {
                $items[] = ['product_name' => trim($m[1]), 'qty' => (int) $m[2]];
                continue;
            }

            if (str_contains($part, 'cappuccino') || str_contains($part, 'croissant') || str_contains($part, 'kopi')) {
                $items[] = ['product_name' => $part, 'qty' => 1];
            }
        }

        return array_values(array_filter($items, fn (array $item): bool => $item['product_name'] !== '' && $item['qty'] > 0));
    }
}
