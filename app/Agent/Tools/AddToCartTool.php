<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\ToolInterface;
use App\Helpers\Currency;
use App\Models\CartModel;
use App\Models\CustomerModel;
use App\Models\MenuModel;
use App\Models\OrderModel;

final class AddToCartTool implements ToolInterface
{
    private CartModel $cartModel;
    private MenuModel $menuModel;
    private CustomerModel $customerModel;
    private OrderModel $orderModel;

    public function __construct()
    {
        $this->cartModel = new CartModel();
        $this->menuModel = new MenuModel();
        $this->customerModel = new CustomerModel();
        $this->orderModel = new OrderModel();
    }

    public function getName(): string
    {
        return 'add_to_cart';
    }

    public function getDescription(): string
    {
        return 'Add a clearly identified menu item to the current cart.';
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'menu_query' => ['type' => 'string'],
                'qty' => ['type' => 'integer'],
                'variant_label' => ['type' => 'string'],
                'use_customer_history' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $input, array $context = []): array
    {
        $branchId = (int)($context['branch_id'] ?? 0);
        $customerId = (int)($context['customer']['id'] ?? 0);
        $cart = (array)($context['cart'] ?? []);
        $cartId = (int)($cart['id'] ?? 0);
        $currency = (string)($context['currency'] ?? 'IDR');
        $lang = (string)($context['language'] ?? 'id');
        $message = (string)($context['message'] ?? '');

        if ($branchId <= 0 || $cartId <= 0) {
            return [
                'status' => 'failed',
                'message' => $lang === 'id'
                    ? 'Keranjang belum siap dipakai untuk menambah item.'
                    : 'Cart is not ready for adding items.',
            ];
        }

        $qty = $this->resolveQuantity($input, $message);
        $variantLabel = trim((string)($input['variant_label'] ?? $this->resolveVariantLabel($message)));
        $useCustomerHistory = (bool)($input['use_customer_history'] ?? false);
        $menuQuery = $this->resolveMenuQuery($input, $message);

        $item = null;
        $resolvedFromHistory = false;

        if ($menuQuery !== '') {
            $item = $this->resolveMenuItem($menuQuery, $branchId);
            if (isset($item['status']) && $item['status'] !== 'resolved') {
                return $item;
            }
            $item = (array)($item['item'] ?? []);
        } elseif ($useCustomerHistory) {
            $item = $this->resolveFromCustomerHistory($customerId, $branchId);
            $resolvedFromHistory = !empty($item);
        }

        if (empty($item)) {
            return [
                'status' => 'needs_clarification',
                'message' => $lang === 'id'
                    ? 'Sebutkan nama menu yang mau ditambahkan supaya saya tidak salah memasukkan item.'
                    : 'Please mention the menu name so I can add the correct item.',
            ];
        }

        $variant = $this->resolveVariant($item, $variantLabel);
        if (!empty($item['has_variants']) && $variant === null) {
            $labels = array_map(static fn(array $row): string => (string)($row['label'] ?? ''), (array)($item['variants'] ?? []));
            $list = implode(', ', array_filter($labels));
            return [
                'status' => 'needs_clarification',
                'item' => $item,
                'message' => $lang === 'id'
                    ? 'Menu ini punya beberapa ukuran/variant. Pilih salah satu: ' . $list
                    : 'This menu has multiple sizes/variants. Please choose one: ' . $list,
            ];
        }

        $unitPrice = (float)($variant['effective_price'] ?? $item['effective_price'] ?? $item['price'] ?? 0);
        $cartItemId = $this->cartModel->addItem(
            $cartId,
            (int)$item['id'],
            $qty,
            $unitPrice,
            '',
            isset($variant['id']) ? (int)$variant['id'] : null,
            isset($variant['label']) ? (string)$variant['label'] : null
        );

        return [
            'status' => 'added',
            'cart_item_id' => $cartItemId,
            'qty' => $qty,
            'item' => $item,
            'variant' => $variant,
            'unit_price' => $unitPrice,
            'line_total_formatted' => Currency::format($unitPrice * $qty, $currency),
            'resolved_from_history' => $resolvedFromHistory,
            'cart_items' => $this->cartModel->getItems($cartId),
        ];
    }

    private function resolveQuantity(array $input, string $message): int
    {
        if (isset($input['qty']) && is_numeric($input['qty'])) {
            return max(1, (int)$input['qty']);
        }

        if (preg_match('/\b(\d{1,2})\b/u', $message, $matches) === 1) {
            return max(1, (int)$matches[1]);
        }

        $lower = mb_strtolower($message, 'UTF-8');
        return match (true) {
            str_contains($lower, 'dua') => 2,
            str_contains($lower, 'tiga') => 3,
            str_contains($lower, 'empat') => 4,
            default => 1,
        };
    }

    private function resolveVariantLabel(string $message): string
    {
        $lower = mb_strtolower($message, 'UTF-8');
        foreach (['small', 'regular', 'medium', 'large', 'besar', 'kecil'] as $token) {
            if (preg_match('/\b' . preg_quote($token, '/') . '\b/u', $lower) === 1) {
                return $token;
            }
        }

        return '';
    }

    private function resolveMenuQuery(array $input, string $message): string
    {
        $explicit = trim((string)($input['menu_query'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $query = mb_strtolower($message, 'UTF-8');
        $query = preg_replace('/\b(tambah|tambahkan|pesan|order|beli|mau|dong|ya|tolong|buat|untuk|ke|keranjang|cart|yang|lagi)\b/u', ' ', $query) ?? $query;
        $query = preg_replace('/\b(\d{1,2}|satu|dua|tiga|empat|small|regular|medium|large|besar|kecil)\b/u', ' ', $query) ?? $query;
        $query = preg_replace('/\s+/u', ' ', trim($query)) ?? trim($query);
        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMenuItem(string $menuQuery, int $branchId): array
    {
        $matches = $this->menuModel->searchRelevantByName($menuQuery, $branchId, 5);
        if (empty($matches)) {
            return [
                'status' => 'failed',
                'message' => 'Menu yang diminta tidak ditemukan di cabang ini.',
            ];
        }

        $normalizedQuery = $this->normalize($menuQuery);
        $scored = [];
        foreach ($matches as $match) {
            $scored[] = [
                'item' => $match,
                'score' => $this->scoreCandidate($normalizedQuery, (string)($match['name'] ?? '')),
            ];
        }

        usort($scored, static fn(array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        $top = $scored[0] ?? null;
        $second = $scored[1] ?? null;

        if (!$top || (int)($top['score'] ?? 0) < 45) {
            return [
                'status' => 'needs_clarification',
                'message' => 'Saya belum yakin menu yang kamu maksud. Coba sebutkan nama menu lebih lengkap.',
            ];
        }

        if ($second && abs((int)$top['score'] - (int)$second['score']) <= 5) {
            $options = implode(', ', array_map(static fn(array $row): string => (string)($row['name'] ?? '-'), array_slice($matches, 0, 3)));
            return [
                'status' => 'needs_clarification',
                'message' => 'Ada beberapa menu yang mirip. Maksudmu yang mana: ' . $options . '?',
            ];
        }

        return [
            'status' => 'resolved',
            'item' => $top['item'],
        ];
    }

    private function resolveFromCustomerHistory(int $customerId, int $branchId): array
    {
        $favoriteIds = array_values(array_filter(array_map('intval', $this->customerModel->getFavoriteItems($customerId))));
        foreach ($favoriteIds as $favoriteId) {
            $item = $this->menuModel->getItemForBranch($favoriteId, $branchId);
            if ($item && (bool)($item['effective_available'] ?? false)) {
                return $item;
            }
        }

        $recentIds = $this->orderModel->getRecentMenuItemIdsForBranch($customerId, $branchId, 3);
        foreach ($recentIds as $recentId) {
            $item = $this->menuModel->getItemForBranch((int)$recentId, $branchId);
            if ($item && (bool)($item['effective_available'] ?? false)) {
                return $item;
            }
        }

        return [];
    }

    private function resolveVariant(array $item, string $variantLabel): ?array
    {
        $variants = (array)($item['variants'] ?? []);
        if (empty($variants)) {
            return null;
        }

        if ($variantLabel === '') {
            return count($variants) === 1 ? $variants[0] : null;
        }

        $needle = $this->normalize($variantLabel);
        foreach ($variants as $variant) {
            $label = $this->normalize((string)($variant['label'] ?? ''));
            $slug = $this->normalize((string)($variant['slug'] ?? ''));
            if ($needle !== '' && ($needle === $label || $needle === $slug || str_contains($label, $needle) || str_contains($slug, $needle))) {
                return $variant;
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    private function scoreCandidate(string $query, string $candidate): int
    {
        $candidateNormalized = $this->normalize($candidate);
        if ($query === '' || $candidateNormalized === '') {
            return 0;
        }

        if ($query === $candidateNormalized) {
            return 100;
        }

        if (str_contains($candidateNormalized, $query) || str_contains($query, $candidateNormalized)) {
            return 75;
        }

        $queryTokens = array_values(array_filter(explode(' ', $query)));
        $candidateTokens = array_values(array_filter(explode(' ', $candidateNormalized)));
        $hits = 0;
        foreach ($queryTokens as $token) {
            if (in_array($token, $candidateTokens, true)) {
                $hits++;
            }
        }

        if (empty($queryTokens)) {
            return 0;
        }

        return (int)round(($hits / count($queryTokens)) * 60);
    }
}
