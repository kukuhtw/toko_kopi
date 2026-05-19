<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\ToolInterface;
use App\Models\CustomerModel;
use App\Models\MenuModel;
use App\Models\OrderModel;

final class RecommendMenuTool implements ToolInterface
{
    private MenuModel $menuModel;
    private CustomerModel $customerModel;
    private OrderModel $orderModel;

    public function __construct()
    {
        $this->menuModel = new MenuModel();
        $this->customerModel = new CustomerModel();
        $this->orderModel = new OrderModel();
    }

    public function getName(): string
    {
        return 'recommend_menu';
    }

    public function getDescription(): string
    {
        return 'Recommend menu items based on customer budget and taste preference.';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'budget' => ['type' => 'number'],
                'flavor' => ['type' => 'string'],
                'temperature' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $input, array $context = []): array
    {
        $branchId = (int)($context['branch_id'] ?? 0);
        $customerId = (int)($context['customer']['id'] ?? 0);
        $message = mb_strtolower(trim((string)($context['message'] ?? '')), 'UTF-8');
        $budget = $this->resolveBudget($input, $message);
        $preferences = $this->resolvePreferences($input, $message);
        $customerSignals = $this->resolveCustomerSignals($customerId, $branchId, $message);

        $items = $branchId > 0 ? $this->menuModel->getMenuForBranch($branchId) : [];
        $items = array_values(array_filter($items, static function (array $item): bool {
            return (bool)($item['effective_available'] ?? false);
        }));

        $ranked = [];
        foreach ($items as $item) {
            $score = $this->scoreItem($item, $budget, $preferences, $customerSignals);
            if ($score <= 0) {
                continue;
            }
            $item['_recommendation_score'] = $score;
            $ranked[] = $item;
        }

        usort($ranked, static function (array $a, array $b): int {
            $scoreCompare = ($b['_recommendation_score'] ?? 0) <=> ($a['_recommendation_score'] ?? 0);
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return ((float)($a['effective_price'] ?? 0)) <=> ((float)($b['effective_price'] ?? 0));
        });

        $recommendations = array_slice(array_map(static function (array $item): array {
            unset($item['_recommendation_score']);
            return $item;
        }, $ranked), 0, 3);

        return [
            'budget' => $budget,
            'preferences' => $preferences,
            'customer_signals' => $customerSignals,
            'recommendations' => $recommendations,
        ];
    }

    private function resolveBudget(array $input, string $message): ?float
    {
        if (isset($input['budget']) && is_numeric($input['budget'])) {
            return max(0.0, (float)$input['budget']);
        }

        if (preg_match('/\b(\d{2,6})\b/u', $message, $m) === 1) {
            return (float)$m[1];
        }

        if (preg_match('/\b(\d+)\s*(rb|ribu|k)\b/u', $message, $m) === 1) {
            return (float)$m[1] * 1000;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function resolvePreferences(array $input, string $message): array
    {
        $flavor = trim((string)($input['flavor'] ?? ''));
        $temperature = trim((string)($input['temperature'] ?? ''));

        if ($flavor === '') {
            if (preg_match('/\b(manis|sweet)\b/u', $message)) {
                $flavor = 'sweet';
            } elseif (preg_match('/\b(pahit|strong|bold)\b/u', $message)) {
                $flavor = 'bold';
            } elseif (preg_match('/\b(creamy|susu|milky)\b/u', $message)) {
                $flavor = 'milky';
            } elseif (preg_match('/\b(segar|fresh|ringan|light)\b/u', $message)) {
                $flavor = 'light';
            }
        }

        if ($temperature === '') {
            if (preg_match('/\b(dingin|cold|ice|iced)\b/u', $message)) {
                $temperature = 'cold';
            } elseif (preg_match('/\b(panas|hot|warm)\b/u', $message)) {
                $temperature = 'hot';
            }
        }

        return [
            'flavor' => $flavor,
            'temperature' => $temperature,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveCustomerSignals(int $customerId, int $branchId, string $message): array
    {
        if ($customerId <= 0 || $branchId <= 0) {
            return [
                'favorite_item_ids' => [],
                'recent_item_ids' => [],
                'preferred_category_ids' => [],
                'history_request' => false,
                'has_customer_history' => false,
            ];
        }

        $favoriteItemIds = array_values(array_filter(array_map('intval', $this->customerModel->getFavoriteItems($customerId))));
        $recentItemIds = array_values(array_filter(array_map('intval', $this->orderModel->getRecentMenuItemIdsForBranch($customerId, $branchId, 5))));
        $historyRequest = preg_match('/\b(mirip|favorit|langganan|biasanya|seperti biasa|kayak kemarin|seperti kemarin|yang sama)\b/u', $message) === 1;

        $preferredCategoryIds = [];
        $seedIds = array_values(array_unique(array_merge(array_slice($favoriteItemIds, 0, 3), array_slice($recentItemIds, 0, 3))));
        foreach ($seedIds as $menuItemId) {
            $item = $this->menuModel->getItemForBranch($menuItemId, $branchId);
            if ($item && !empty($item['category_id'])) {
                $preferredCategoryIds[] = (int)$item['category_id'];
            }
        }

        return [
            'favorite_item_ids' => $favoriteItemIds,
            'recent_item_ids' => $recentItemIds,
            'preferred_category_ids' => array_values(array_unique($preferredCategoryIds)),
            'history_request' => $historyRequest,
            'has_customer_history' => !empty($favoriteItemIds) || !empty($recentItemIds),
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $preferences
     * @param array<string, mixed> $customerSignals
     */
    private function scoreItem(array $item, ?float $budget, array $preferences, array $customerSignals): int
    {
        $itemId = (int)($item['id'] ?? 0);
        $categoryId = (int)($item['category_id'] ?? 0);
        $name = mb_strtolower((string)($item['name'] ?? ''), 'UTF-8');
        $desc = mb_strtolower((string)($item['description'] ?? ''), 'UTF-8');
        $category = mb_strtolower((string)($item['category_name'] ?? ''), 'UTF-8');
        $price = (float)($item['effective_price'] ?? $item['price'] ?? 0);

        $score = 20;

        if ($budget !== null) {
            if ($price <= $budget) {
                $score += 50;
                $score += max(0, (int)(20 - abs($budget - $price) / max(1, $budget) * 20));
            } else {
                $score -= 80;
            }
        }

        $haystack = $name . ' ' . $desc . ' ' . $category;

        $flavor = $preferences['flavor'] ?? '';
        if ($flavor === 'sweet' && preg_match('/\b(manis|sweet|caramel|aren|vanilla|mocha|cokelat|chocolate)\b/u', $haystack)) {
            $score += 35;
        } elseif ($flavor === 'bold' && preg_match('/\b(espresso|americano|kopi hitam|strong|bold|dark)\b/u', $haystack)) {
            $score += 35;
        } elseif ($flavor === 'milky' && preg_match('/\b(latte|susu|milk|milky|cappuccino|cream)\b/u', $haystack)) {
            $score += 35;
        } elseif ($flavor === 'light' && preg_match('/\b(tea|lemon|fresh|fruit|ringan|light)\b/u', $haystack)) {
            $score += 30;
        }

        $temperature = $preferences['temperature'] ?? '';
        if ($temperature === 'cold' && preg_match('/\b(iced|ice|dingin|cold)\b/u', $haystack)) {
            $score += 20;
        } elseif ($temperature === 'hot' && preg_match('/\b(hot|panas|espresso|americano|latte|cappuccino)\b/u', $haystack)) {
            $score += 20;
        }

        $favoriteItemIds = (array)($customerSignals['favorite_item_ids'] ?? []);
        $recentItemIds = (array)($customerSignals['recent_item_ids'] ?? []);
        $preferredCategoryIds = (array)($customerSignals['preferred_category_ids'] ?? []);
        $historyRequest = (bool)($customerSignals['history_request'] ?? false);

        if (in_array($itemId, $favoriteItemIds, true)) {
            $score += $historyRequest ? 120 : 60;
        } elseif (in_array($itemId, $recentItemIds, true)) {
            $score += $historyRequest ? 95 : 45;
        }

        if ($categoryId > 0 && in_array($categoryId, $preferredCategoryIds, true)) {
            $score += $historyRequest ? 35 : 18;
        }

        return max(0, $score);
    }
}
