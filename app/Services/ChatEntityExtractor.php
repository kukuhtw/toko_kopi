<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Currency;

class ChatEntityExtractor
{
    private IntentDetector $detector;

    public function __construct(?IntentDetector $detector = null)
    {
        $this->detector = $detector ?? new IntentDetector();
    }

    public function extract(string $message, string $defaultCurrency = 'IDR'): array
    {
        $defaultCurrency = Currency::code($defaultCurrency);
        $currencies = $this->extractCurrencies($message, $defaultCurrency);
        $prices = $this->extractPrices($message, $defaultCurrency);
        $products = $this->extractProducts($message, $prices, $defaultCurrency);
        $budget = $this->extractBudget($message, $prices, $defaultCurrency);

        return [
            'products' => $products,
            'prices' => $prices,
            'currencies' => $currencies,
            'primary_currency' => $currencies[0]['code'] ?? $defaultCurrency,
            'budget' => $budget,
            'variant_candidates' => $this->extractVariantCandidates($message),
        ];
    }

    private function extractProducts(string $message, array $prices, string $defaultCurrency): array
    {
        $parsed = $this->detector->extractMultipleItems($message);
        $products = [];

        foreach ($parsed as $index => $item) {
            $query = $this->normalizeProductQuery((string)($item['item_query'] ?? ''));
            if ($query === '') {
                continue;
            }

            $assignedPrice = $this->resolveAssignedPrice($prices, $index, count($parsed));
            $products[] = [
                'name_candidate' => $query,
                'qty' => max(1, (int)($item['qty'] ?? 1)),
                'variant_label' => $this->normalizeVariantLabel((string)($item['variant_label'] ?? '')),
                'mentioned_price' => $assignedPrice['amount'] ?? null,
                'mentioned_currency' => $assignedPrice['currency'] ?? $defaultCurrency,
                'price_raw' => $assignedPrice['raw'] ?? '',
            ];
        }

        return $products;
    }

    private function normalizeProductQuery(string $query): string
    {
        $query = mb_strtolower(trim($query), 'UTF-8');
        $query = preg_replace(
            '/\b(apa|itu|apakah|tolong|please|plis|jelaskan|ceritakan|seperti|detail|info|deskripsi|tentang|harga|price|cost|berapa|promo|diskon|voucher|kode|yang|ada|di bawah|dibawah|under|below|less than|max|maks|di atas|diatas|over|above|more than|min|minimal|at least|mulai dari|sekitar|around|about|kisaran)\b/u',
            ' ',
            $query
        );
        $query = preg_replace('/\b(?:rp|idr|rupiah|usd|sgd|aud|us\$|s\$|a\$)\s*[0-9][0-9\.\,]*\b/iu', ' ', (string)$query);
        $query = preg_replace('/(?<![A-Za-z])(?:s\$|a\$|\$)\s*[0-9][0-9\.\,]*\b/u', ' ', (string)$query);
        $query = preg_replace('/\b[0-9]+(?:[\.\,][0-9]+)?\s*(?:rb|ribu|k)?\b/u', ' ', (string)$query);
        $query = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', (string)$query);
        $query = preg_replace('/\s+/u', ' ', (string)$query);

        return trim((string)$query);
    }

    private function resolveAssignedPrice(array $prices, int $productIndex, int $productCount): ?array
    {
        if (empty($prices)) {
            return null;
        }

        if (count($prices) === 1) {
            return $prices[0];
        }

        if (count($prices) === $productCount && isset($prices[$productIndex])) {
            return $prices[$productIndex];
        }

        return null;
    }

    private function extractCurrencies(string $message, string $defaultCurrency): array
    {
        $detected = [];
        $patterns = [
            'IDR' => '/\b(idr|rp|rupiah)\b/iu',
            'USD' => '/\b(usd|us\$)\b/iu',
            'SGD' => '/\b(sgd|s\$)\b/iu',
            'AUD' => '/\b(aud|a\$)\b/iu',
        ];

        foreach ($patterns as $code => $pattern) {
            if (preg_match($pattern, $message) === 1) {
                $detected[] = ['code' => $code, 'source' => 'text'];
            }
        }

        if (preg_match('/(?<![A-Za-z])\$(?=\s*\d)/u', $message) === 1) {
            $detected[] = ['code' => 'USD', 'source' => 'symbol'];
        }

        $unique = [];
        foreach ($detected as $row) {
            $unique[$row['code']] = $row;
        }

        if (!isset($unique[$defaultCurrency])) {
            $unique = [$defaultCurrency => ['code' => $defaultCurrency, 'source' => 'branch_default']] + $unique;
        }

        return array_values($unique);
    }

    private function extractPrices(string $message, string $defaultCurrency): array
    {
        $prices = [];

        $patternMap = [
            'IDR' => [
                '/\b(?:rp|idr|rupiah)\s*([0-9][0-9\.\,]*)\b/iu',
                '/\b([0-9]{1,3}(?:[\.\,][0-9]{3})+)\s*(?:rp|idr|rupiah)\b/iu',
            ],
            'USD' => [
                '/\b(?:usd|us\$)\s*([0-9][0-9\.\,]*)\b/iu',
            ],
            'SGD' => [
                '/\b(?:sgd|s\$)\s*([0-9][0-9\.\,]*)\b/iu',
            ],
            'AUD' => [
                '/\b(?:aud|a\$)\s*([0-9][0-9\.\,]*)\b/iu',
            ],
        ];

        foreach ($patternMap as $currency => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $message, $matches, PREG_SET_ORDER) !== false) {
                    foreach ($matches as $match) {
                        $amount = $this->parseAmount((string)($match[1] ?? ''), $currency);
                        if ($amount === null) {
                            continue;
                        }
                        $prices[] = [
                            'amount' => $amount,
                            'currency' => $currency,
                            'raw' => (string)$match[0],
                        ];
                    }
                }
            }
        }

        if (preg_match_all('/(?<![A-Za-z])\$\s*([0-9][0-9\.\,]*)\b/u', $message, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $amount = $this->parseAmount((string)($match[1] ?? ''), 'USD');
                if ($amount === null) {
                    continue;
                }
                $prices[] = [
                    'amount' => $amount,
                    'currency' => 'USD',
                    'raw' => (string)$match[0],
                ];
            }
        }

        if (preg_match_all('/\b([0-9]+(?:[\.,][0-9]+)?)\s*(rb|ribu|k)\b/iu', $message, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $base = (float)str_replace(',', '.', (string)$match[1]);
                $prices[] = [
                    'amount' => $base * 1000,
                    'currency' => 'IDR',
                    'raw' => (string)$match[0],
                ];
            }
        }

        if (empty($prices) && preg_match('/\b([0-9]{4,})\b/u', $message, $match) === 1) {
            $amount = $this->parseAmount((string)$match[1], $defaultCurrency);
            if ($amount !== null) {
                $prices[] = [
                    'amount' => $amount,
                    'currency' => $defaultCurrency,
                    'raw' => (string)$match[0],
                ];
            }
        }

        return $this->deduplicatePrices($prices);
    }

    private function deduplicatePrices(array $prices): array
    {
        $unique = [];
        foreach ($prices as $price) {
            $key = ($price['currency'] ?? 'IDR') . ':' . number_format((float)($price['amount'] ?? 0), 2, '.', '');
            $unique[$key] = $price;
        }

        return array_values($unique);
    }

    private function extractBudget(string $message, array $prices, string $defaultCurrency): ?array
    {
        if (empty($prices)) {
            return null;
        }

        $lower = mb_strtolower($message, 'UTF-8');
        $operator = null;

        if (preg_match('/\b(di bawah|dibawah|under|below|less than|max|maks|termurah|murah)\b/u', $lower) === 1) {
            $operator = 'lte';
        } elseif (preg_match('/\b(di atas|diatas|over|above|more than|min|minimal|at least|mulai dari)\b/u', $lower) === 1) {
            $operator = 'gte';
        } elseif (preg_match('/\b(sekitar|around|about|kisaran)\b/u', $lower) === 1) {
            $operator = 'approx';
        }

        if ($operator === null) {
            return null;
        }

        $primary = $prices[0];
        return [
            'operator' => $operator,
            'amount' => (float)($primary['amount'] ?? 0),
            'currency' => Currency::code((string)($primary['currency'] ?? $defaultCurrency)),
            'raw' => (string)($primary['raw'] ?? ''),
        ];
    }

    private function extractVariantCandidates(string $message): array
    {
        $variants = [];
        $map = [
            'small' => '/\b(small|sm|kecil)\b/iu',
            'medium' => '/\b(medium|md|sedang|regular|reguler)\b/iu',
            'large' => '/\b(large|lg|besar)\b/iu',
        ];

        foreach ($map as $label => $pattern) {
            if (preg_match($pattern, $message) === 1) {
                $variants[] = $label;
            }
        }

        return $variants;
    }

    private function normalizeVariantLabel(string $variantLabel): ?string
    {
        $variantLabel = mb_strtolower(trim($variantLabel), 'UTF-8');
        if ($variantLabel === '') {
            return null;
        }

        return match ($variantLabel) {
            'sm', 'kecil' => 'small',
            'md', 'sedang', 'regular', 'reguler' => 'medium',
            'lg', 'besar' => 'large',
            default => $variantLabel,
        };
    }

    private function parseAmount(string $rawAmount, string $currency): ?float
    {
        $rawAmount = trim($rawAmount);
        if ($rawAmount === '') {
            return null;
        }

        $hasComma = str_contains($rawAmount, ',');
        $hasDot = str_contains($rawAmount, '.');

        if ($currency === 'IDR') {
            $normalized = str_replace([',', '.'], '', $rawAmount);
            return is_numeric($normalized) ? (float)$normalized : null;
        }

        if ($hasComma && $hasDot) {
            $normalized = str_replace(',', '', $rawAmount);
        } elseif ($hasComma) {
            $normalized = str_replace(',', '.', $rawAmount);
        } else {
            $normalized = $rawAmount;
        }

        return is_numeric($normalized) ? (float)$normalized : null;
    }
}
