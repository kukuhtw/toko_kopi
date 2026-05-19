<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\ToolInterface;
use App\Models\CartModel;

final class GetCartSnapshotTool implements ToolInterface
{
    private CartModel $cartModel;

    public function __construct()
    {
        $this->cartModel = new CartModel();
    }

    public function getName(): string
    {
        return 'get_cart_snapshot';
    }

    public function getDescription(): string
    {
        return 'Read the latest cart items and totals for the current customer session.';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $input, array $context = []): array
    {
        $sessionKey = (string)($context['session_key'] ?? '');
        if ($sessionKey === '') {
            return ['cart' => null, 'items' => [], 'total' => 0.0];
        }

        $cart = $this->cartModel->getBySession($sessionKey);
        if (!$cart) {
            return ['cart' => null, 'items' => [], 'total' => 0.0];
        }

        return [
            'cart' => $cart,
            'items' => $this->cartModel->getItems((int)$cart['id']),
            'total' => $this->cartModel->getTotal((int)$cart['id']),
        ];
    }
}
