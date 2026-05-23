<?php

declare(strict_types=1);

namespace App\Skills;

use App\Models\MenuModel;
use App\Helpers\Currency;
use App\Services\MenuRagResponder;

class MenuSkill implements SkillInterface
{
    private MenuModel $menuModel;
    private MenuRagResponder $ragResponder;

    public function __construct()
    {
        $this->menuModel = new MenuModel();
        $this->ragResponder = new MenuRagResponder();
    }

    public function canHandle(string $intent): bool
    {
        return in_array($intent, ['tanya_menu', 'tanya_harga']);
    }

    public function handle(array $ctx): array
    {
        $branchId = $ctx['branch_id'];
        $lang     = $ctx['language'] ?? 'id';
        $currency = $ctx['currency'] ?? 'IDR';
        $intent   = $ctx['intent'];
        $lower    = mb_strtolower($ctx['message'], 'UTF-8');
        $entities = is_array($ctx['entities'] ?? null) ? $ctx['entities'] : [];

        // ── Item description / explain requests ───────────────────────────────
        if ($this->looksLikeDescriptionRequest($lower)) {
            $query = $this->extractDescriptionQuery($lower);
            if ($query === '') {
                $query = $this->extractFirstEntityProductQuery($entities);
            }
            if ($query !== '') {
                $items = $this->findDescribedItems($query, $branchId);
                if (empty($items) && $this->ragResponder->isEnabled()) {
                    $items = $this->ragResponder->resolveItems($ctx['message'], $branchId);
                }
                if (!empty($items)) {
                    $ragReply = $this->ragResponder->composeDescriptionReply($ctx, $items);
                    if ($ragReply !== null) {
                        return [
                            'reply' => $ragReply,
                            'state' => 'idle',
                            'action_result' => $items,
                            'conv_context' => [
                                'last_topic' => 'menu',
                                'last_menu_items' => array_map(static fn(array $item): array => [
                                    'id' => (int)($item['id'] ?? 0),
                                    'name' => (string)($item['name'] ?? ''),
                                ], $items),
                            ],
                        ];
                    }

                    $blocks = [];
                    foreach ($items as $item) {
                        $desc  = !empty($item['description']) ? "\n_{$item['description']}_" : '';
                        $blocks[] = "*{$item['name']}* — " . $this->formatItemPrice($item, $currency) . $desc;
                    }

                    $reply = implode("\n\n", $blocks);
                    if (count($items) === 1) {
                        $reply .= "\n\n" . $this->buildOrderHintForItem($items[0], $lang);
                    } else {
                        $reply .= "\n\n" . $this->t($lang, 'order_hint_generic');
                    }

                    return [
                        'reply' => $reply,
                        'state' => 'idle',
                        'action_result' => $items,
                        'conv_context' => [
                            'last_topic' => 'menu',
                            'last_menu_items' => array_map(static fn(array $item): array => [
                                'id' => (int)($item['id'] ?? 0),
                                'name' => (string)($item['name'] ?? ''),
                            ], $items),
                        ],
                    ];
                }
            }
        }

        // ── Price inquiry ──────────────────────────────────────────────────────
        if ($intent === 'tanya_harga') {
            $budget = is_array($entities['budget'] ?? null) ? $entities['budget'] : null;
            if ($budget !== null) {
                $budgetItems = $this->findItemsByBudget($branchId, $budget, $this->extractBudgetQuery($lower));
                if (!empty($budgetItems)) {
                    return [
                        'reply' => $this->buildBudgetView($budgetItems, $budget, $currency, $lang),
                        'state' => 'idle',
                        'action_result' => $budgetItems,
                        'conv_context' => [
                            'last_topic' => 'menu',
                            'last_menu_items' => array_map(static fn(array $item): array => [
                                'id' => (int)($item['id'] ?? 0),
                                'name' => (string)($item['name'] ?? ''),
                            ], $budgetItems),
                        ],
                    ];
                }
            }

            $query = trim(str_replace(
                ['harga', 'berapa', 'price', 'cost', 'how much'],
                '',
                $lower
            ));
            if ($query === '') {
                $query = $this->extractFirstEntityProductQuery($entities);
            }
            if (!empty($query)) {
                $items = $this->menuModel->searchRelevantByName($query, $branchId, 5);
                if (!empty($items)) {
                $lines = array_map(
                        fn($i) => "• {$i['name']}: " . $this->formatItemPrice($i, $currency),
                        array_slice($items, 0, 5)
                    );
                    return [
                        'reply'         => $this->t($lang, 'price_result') . "\n" . implode("\n", $lines),
                        'state'         => 'idle',
                        'action_result' => $items,
                        'conv_context'  => [
                            'last_topic' => 'menu',
                            'last_menu_items' => array_map(static fn(array $item): array => [
                                'id' => (int)($item['id'] ?? 0),
                                'name' => (string)($item['name'] ?? ''),
                            ], $items),
                        ],
                    ];
                }
            }
        }

        // ── Load categories with counts ────────────────────────────────────────
        $directItem = $this->findDirectMenuItem($lower, $branchId);
        if ($directItem !== null) {
            $desc  = !empty($directItem['description']) ? "\n_{$directItem['description']}_" : '';
            return [
                'reply' => "*{$directItem['name']}* - " . $this->formatItemPrice($directItem, $currency) . "{$desc}\n\n" . $this->buildOrderHintForItem($directItem, $lang),
                'state' => 'idle',
                'action_result' => [$directItem],
                'conv_context' => [
                    'last_topic' => 'menu',
                    'last_menu_items' => [[
                        'id' => (int)($directItem['id'] ?? 0),
                        'name' => (string)($directItem['name'] ?? ''),
                    ]],
                ],
            ];
        }

        $categories = $this->menuModel->getCategoriesWithCount($branchId);
        if (empty($categories)) {
            return ['reply' => $this->t($lang, 'no_menu'), 'state' => 'idle', 'action_result' => null];
        }

        // ── Detect category mention in message ─────────────────────────────────
        $matchedCats = [];
        foreach ($categories as $cat) {
            if (mb_stripos($lower, mb_strtolower($cat['name'], 'UTF-8'), 0, 'UTF-8') !== false) {
                $matchedCats[] = $cat;
            }
        }

        if (!empty($matchedCats)) {
            $matchedItems = $this->collectCategoryItems($matchedCats, $branchId);
            return [
                'reply'         => $this->buildCategoryView($matchedCats, $branchId, $currency, $lang),
                'state'         => 'idle',
                'action_result' => $matchedItems,
                'conv_context'  => [
                    'last_topic' => 'menu',
                    'last_menu_items' => array_map(static fn(array $item): array => [
                        'id' => (int)($item['id'] ?? 0),
                        'name' => (string)($item['name'] ?? ''),
                    ], $matchedItems),
                ],
            ];
        }

        $recommendedCats = $this->detectRecommendationCategories($categories, $lower);
        if (!empty($recommendedCats)) {
            return [
                'reply'         => $this->buildRecommendationView($recommendedCats, $branchId, $currency, $lang),
                'state'         => 'idle',
                'action_result' => $recommendedCats,
                'conv_context'  => ['last_topic' => 'menu'],
            ];
        }

        $budget = is_array($entities['budget'] ?? null) ? $entities['budget'] : null;
        if ($budget !== null) {
            $budgetItems = $this->findItemsByBudget($branchId, $budget, $this->extractBudgetQuery($lower));
            if (!empty($budgetItems)) {
                return [
                    'reply' => $this->buildBudgetView($budgetItems, $budget, $currency, $lang),
                    'state' => 'idle',
                    'action_result' => $budgetItems,
                    'conv_context' => [
                        'last_topic' => 'menu',
                        'last_menu_items' => array_map(static fn(array $item): array => [
                            'id' => (int)($item['id'] ?? 0),
                            'name' => (string)($item['name'] ?? ''),
                        ], $budgetItems),
                    ],
                ];
            }
        }

        $itemQuery = $this->extractItemListQuery($lower);
        if ($itemQuery === '') {
            $itemQuery = $this->extractFirstEntityProductQuery($entities);
        }
        if ($itemQuery !== '') {
            $items = $this->menuModel->searchRelevantByName($itemQuery, $branchId, 8);
            if (!empty($items)) {
                return [
                    'reply'         => $this->buildItemListView($items, $currency, $lang),
                    'state'         => 'idle',
                    'action_result' => $items,
                    'conv_context'  => [
                        'last_topic' => 'menu',
                        'last_menu_items' => array_map(static fn(array $item): array => [
                            'id' => (int)($item['id'] ?? 0),
                            'name' => (string)($item['name'] ?? ''),
                        ], $items),
                    ],
                ];
            }
        }

        // ── Default: show category overview (never dump all 600 items) ─────────
        return [
            'reply'         => $this->buildCategoryOverview($categories, $lang),
            'state'         => 'idle',
            'action_result' => $categories,
            'conv_context'  => ['last_topic' => 'menu'],
        ];
    }

    private function buildCategoryOverview(array $categories, string $lang): string
    {
        $total = (int)array_sum(array_column($categories, 'item_count'));
        $count = count($categories);

        if ($lang === 'en') {
            $header = "We have *{$total} menu items* across {$count} categories:\n";
            $footer = "\nType a category name to browse, or just order directly — e.g. _order 1 latte_ ☕";
        } else {
            $header = "Kami punya *{$total} menu* dalam {$count} kategori:\n";
            $footer = "\nKetik nama kategori untuk lihat pilihan, atau langsung pesan — contoh: _pesan 1 latte_ ☕";
        }

        $lines = [$header];
        foreach ($categories as $cat) {
            $n      = (int)$cat['item_count'];
            $label  = $lang === 'en' ? 'items' : 'item';
            $lines[] = "• *{$cat['name']}* ({$n} {$label})";
        }
        $lines[] = $footer;

        return implode("\n", $lines);
    }

    private function buildCategoryView(array $cats, int $branchId, string $currency, string $lang): string
    {
        $lines = [];

        foreach ($cats as $cat) {
            $total = max(1, (int)($cat['item_count'] ?? 0));
            $items = $this->menuModel->getMenuByCategory($branchId, (int)$cat['id'], $total);
            if (empty($items)) {
                continue;
            }

            $header = "\n*{$cat['name']}*";
            $lines[] = $header;

            foreach ($items as $item) {
                $lines[] = "• {$item['name']} — " . $this->formatItemPrice($item, $currency);
            }
        }

        if (empty($lines)) {
            return $this->t($lang, 'no_menu');
        }

        $lines[] = "\n" . $this->t($lang, 'order_hint_generic');
        return implode("\n", $lines);
    }

    private function collectCategoryItems(array $cats, int $branchId): array
    {
        $items = [];
        foreach ($cats as $cat) {
            $total = max(1, (int)($cat['item_count'] ?? 0));
            foreach ($this->menuModel->getMenuByCategory($branchId, (int)($cat['id'] ?? 0), $total) as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function buildItemListView(array $items, string $currency, string $lang): string
    {
        $header = $lang === 'en'
            ? "Here are the menu items I found:\n"
            : "Ini menu yang saya temukan:\n";

        $lines = [$header];
        foreach ($items as $item) {
            $lines[] = "• {$item['name']} — " . $this->formatItemPrice($item, $currency);
        }

        $lines[] = "\n" . $this->t($lang, 'order_hint_generic');
        return implode("\n", $lines);
    }

    private function buildRecommendationView(array $cats, int $branchId, string $currency, string $lang): string
    {
        $header = $lang === 'en'
            ? "Here are some recommendations you may like:\n"
            : "Ini beberapa rekomendasi yang mungkin cocok buat kamu:\n";

        $lines = [$header];
        foreach ($cats as $cat) {
            $items = $this->menuModel->getMenuByCategory($branchId, (int)$cat['id'], 5);
            if (empty($items)) {
                continue;
            }
            $lines[] = "\n*{$cat['name']}*";
            foreach ($items as $item) {
                $lines[] = "• {$item['name']} — " . $this->formatItemPrice($item, $currency);
            }
        }

        $lines[] = "\n" . $this->t($lang, 'order_hint_generic');
        return implode("\n", $lines);
    }

    private function buildBudgetView(array $items, array $budget, string $currency, string $lang): string
    {
        $amount = Currency::format((float)($budget['amount'] ?? 0), (string)($budget['currency'] ?? $currency));
        $header = match ($budget['operator'] ?? 'lte') {
            'gte' => $lang === 'en'
                ? "Here are menu items from {$amount} and above:\n"
                : "Ini menu mulai dari {$amount} ke atas:\n",
            'approx' => $lang === 'en'
                ? "Here are menu items around {$amount}:\n"
                : "Ini menu di kisaran {$amount}:\n",
            default => $lang === 'en'
                ? "Here are menu items up to {$amount}:\n"
                : "Ini menu sampai {$amount}:\n",
        };

        $lines = [$header];
        foreach ($items as $item) {
            $lines[] = "• {$item['name']} — " . $this->formatItemPrice($item, $currency);
        }

        $lines[] = "\n" . $this->t($lang, 'order_hint_generic');
        return implode("\n", $lines);
    }

    private function looksLikeDescriptionRequest(string $lower): bool
    {
        return (bool)preg_match(
            '/\bitu\s+apa\b|\bapa\s+itu\b|\bitu\s+minuman\b|\bseperti\s+apa\b|\bisi(?:nya+a*|nya|)\s+apa\b|\bapa\s+isi(?:nya+a*|nya|)\b|\b(jelaskan|deskripsi|detail|info|ceritakan)\b/u',
            $lower
        );
    }

    private function extractDescriptionQuery(string $lower): string
    {
        $query = preg_replace(
            '/\b(itu|apa|apakah|tolong|jelaskan|ceritakan|seperti|minuman|makanan|nih|ya|dong|menu|paketnya|detail|info|deskripsi|tentang|minta|isi|isinya+a*|isinya)\b/u',
            ' ',
            $lower
        );
        $query = trim(preg_replace('/[^\p{L}\p{N}\s,&+-]/u', ' ', $query));
        return trim(preg_replace('/\s+/u', ' ', $query));
    }

    private function extractItemListQuery(string $lower): string
    {
        $query = preg_replace(
            '/\b(menu|list|list of|show|show me|see|view|ada|do you have|have|any|your|the|please|tolong|dong|nih|ya|item|items|produk|product|products)\b/u',
            ' ',
            $lower
        );
        $query = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', (string)$query);
        $query = preg_replace('/\s+/u', ' ', (string)$query);
        return trim((string)$query);
    }

    private function extractBudgetQuery(string $lower): string
    {
        $query = preg_replace(
            '/\b(harga|berapa|price|cost|how much|di bawah|dibawah|under|below|less than|max|maks|termurah|murah|di atas|diatas|over|above|more than|min|minimal|at least|mulai dari|sekitar|around|about|kisaran|rp|idr|usd|sgd|aud|rupiah)\b/u',
            ' ',
            $lower
        );
        $query = preg_replace('/(?<![A-Za-z])(?:s\$|a\$|\$)\s*[0-9][0-9\.,]*/u', ' ', (string)$query);
        $query = preg_replace('/\b[0-9]+(?:[\.\,][0-9]+)?\s*(?:rb|ribu|k)?\b/u', ' ', (string)$query);
        $query = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', (string)$query);
        $query = preg_replace('/\s+/u', ' ', (string)$query);
        return trim((string)$query);
    }

    private function detectRecommendationCategories(array $categories, string $lower): array
    {
        $wantHot = preg_match('/\b(hot|minuman panas|kopi panas|warm)\b/u', $lower) === 1;
        $wantCold = preg_match('/\b(cold|cool|iced|ice|minuman dingin|kopi dingin)\b/u', $lower) === 1;
        $wantRecommend = preg_match('/\b(recommend|suggest|rekomendasi|sarankan|something)\b/u', $lower) === 1;

        if (!$wantHot && !$wantCold && !$wantRecommend) {
            return [];
        }

        $matched = [];
        foreach ($categories as $cat) {
            $name = mb_strtolower((string)$cat['name'], 'UTF-8');
            if ($wantHot && str_contains($name, 'panas')) {
                $matched[] = $cat;
                continue;
            }
            if ($wantCold && str_contains($name, 'dingin')) {
                $matched[] = $cat;
                continue;
            }
        }

        if (!empty($matched)) {
            return $matched;
        }

        if ($wantRecommend) {
            return array_slice($categories, 0, 2);
        }

        return [];
    }

    private function findDescribedItems(string $query, int $branchId): array
    {
        $parts = preg_split('/\s*,\s*|\s+dan\s+|\s+&\s+|\s+\+\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = array_values(array_filter(array_map(static fn(string $part): string => trim($part), $parts)));

        if (count($parts) <= 1) {
            $parts = [$query];
        }

        $seen = [];
        $items = [];
        $partCount = count($parts);
        $maxItems = max(1, min($partCount, 6));

        foreach ($parts as $part) {
            foreach ($this->menuModel->searchRelevantByName($part, $branchId, 3) as $item) {
                $id = (int)($item['id'] ?? 0);
                if ($id <= 0 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $items[] = $item;
                break;
            }

            if (count($items) >= $maxItems) {
                break;
            }
        }

        if (empty($items) && $partCount > 1) {
            return $this->menuModel->searchRelevantByName($query, $branchId, min(3, $maxItems));
        }

        return $items;
    }

    private function findItemsByBudget(int $branchId, array $budget, string $query = ''): array
    {
        $items = $this->menuModel->getMenuForBranch($branchId);
        $filtered = [];

        foreach ($items as $item) {
            if (!$this->matchesBudget($item, $budget)) {
                continue;
            }
            if ($query !== '' && !$this->matchesBudgetQuery($item, $query)) {
                continue;
            }
            $filtered[] = $item;
        }

        usort($filtered, static function (array $a, array $b): int {
            $left = (float)($a['effective_price'] ?? 0);
            $right = (float)($b['effective_price'] ?? 0);
            if ($left === $right) {
                return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
            }
            return $left <=> $right;
        });

        return array_slice($filtered, 0, 8);
    }

    private function matchesBudget(array $item, array $budget): bool
    {
        $prices = [];
        if (!empty($item['variants']) && is_array($item['variants'])) {
            foreach ($item['variants'] as $variant) {
                $prices[] = (float)($variant['effective_price'] ?? 0);
            }
        } else {
            $prices[] = (float)($item['effective_price'] ?? 0);
        }

        $amount = (float)($budget['amount'] ?? 0);
        $operator = (string)($budget['operator'] ?? 'lte');
        foreach ($prices as $price) {
            if ($operator === 'gte' && $price >= $amount) {
                return true;
            }
            if ($operator === 'approx' && abs($price - $amount) <= max(1000, $amount * 0.15)) {
                return true;
            }
            if ($operator === 'lte' && $price <= $amount) {
                return true;
            }
        }

        return false;
    }

    private function matchesBudgetQuery(array $item, string $query): bool
    {
        $haystack = mb_strtolower(trim(implode(' ', [
            (string)($item['name'] ?? ''),
            (string)($item['category_name'] ?? ''),
            (string)($item['description'] ?? ''),
        ])), 'UTF-8');
        if ($haystack === '') {
            return false;
        }

        foreach (array_filter(explode(' ', $query)) as $token) {
            if (mb_strlen($token, 'UTF-8') < 3) {
                continue;
            }
            if (str_contains($haystack, $token)) {
                return true;
            }
        }

        return false;
    }

    private function findDirectMenuItem(string $lower, int $branchId): ?array
    {
        $query = $this->normalizeLookupQuery($lower);
        if ($query === '' || mb_strlen($query, 'UTF-8') < 4) {
            return null;
        }

        $items = $this->menuModel->searchRelevantByName($query, $branchId, 1);
        if (empty($items)) {
            return null;
        }

        $top = $items[0];
        return $this->normalizeLookupQuery((string)($top['name'] ?? '')) === $query
            ? $top
            : null;
    }

    private function normalizeLookupQuery(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/\b(apa|apakah|dong|nih|ya|menu|tolong|please)\b/u', ' ', $text);
        $text = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', (string)$text);
        $text = preg_replace('/\s+/u', ' ', (string)$text);
        return trim((string)$text);
    }

    private function extractFirstEntityProductQuery(array $entities): string
    {
        $products = $entities['products'] ?? [];
        if (!is_array($products) || empty($products[0]['name_candidate'])) {
            return '';
        }

        return trim((string)$products[0]['name_candidate']);
    }

    private function formatItemPrice(array $item, string $currency): string
    {
        if (!empty($item['variants']) && is_array($item['variants'])) {
            return implode(' | ', array_map(
                static fn(array $variant): string => "{$variant['label']} " . Currency::format((float)($variant['effective_price'] ?? 0), $currency),
                $item['variants']
            ));
        }

        return Currency::format((float)$item['effective_price'], $currency);
    }

    private function buildOrderHintForItem(array $item, string $lang): string
    {
        if (!empty($item['variants']) && is_array($item['variants'])) {
            $first = $item['variants'][0]['label'] ?? 'medium';
            return $lang === 'en'
                ? "Want to order? Type _order 1 {$item['name']} {$first}_"
                : "Mau pesan? Ketik _pesan 1 {$item['name']} {$first}_";
        }

        return $this->t($lang, 'order_hint', (string)$item['name']);
    }

    private function t(string $lang, string $key, string $param = ''): string
    {
        $map = [
            'id' => [
                'no_menu'          => 'Maaf, saat ini menu belum tersedia. Silakan coba lagi nanti.',
                'price_result'     => 'Berikut harga yang kamu tanyakan:',
                'order_hint'       => "Mau pesan? Ketik _pesan 1 {$param}_",
                'order_hint_generic' => 'Mau pesan? Ketik nama menu yang kamu mau, contoh: _pesan 1 latte_',
            ],
            'en' => [
                'no_menu'          => 'Sorry, the menu is not available at the moment.',
                'price_result'     => 'Here are the prices you asked about:',
                'order_hint'       => "Want to order? Type _order 1 {$param}_",
                'order_hint_generic' => 'Want to order? Type the item name, e.g. _order 1 latte_',
            ],
        ];
        return $map[$lang][$key] ?? $map['id'][$key];
    }
}
