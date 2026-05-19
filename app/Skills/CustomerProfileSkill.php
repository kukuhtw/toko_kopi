<?php

declare(strict_types=1);

namespace App\Skills;

use App\Models\CustomerModel;
use App\Models\OrderModel;
use App\Models\MenuModel;
use App\Helpers\Currency;

class CustomerProfileSkill implements SkillInterface
{
    private CustomerModel $customerModel;
    private OrderModel    $orderModel;
    private MenuModel     $menuModel;

    public function __construct()
    {
        $this->customerModel = new CustomerModel();
        $this->orderModel    = new OrderModel();
        $this->menuModel     = new MenuModel();
    }

    public function canHandle(string $intent): bool
    {
        return $intent === 'tanya_status_order';
    }

    public function handle(array $ctx): array
    {
        $customer = $ctx['customer'];
        $lang     = $ctx['language'] ?? 'id';
        $currency = $ctx['currency'] ?? 'IDR';

        $orders = $this->orderModel->getCustomerOrders($customer['id'], 5);

        if (empty($orders)) {
            $reply = $lang === 'id'
                ? 'Kamu belum punya order. Ketik *menu* untuk mulai memesan!'
                : "You don't have any orders yet. Type *menu* to start ordering!";
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => []];
        }

        $header = $lang === 'id' ? "📦 *Order kamu:*\n" : "📦 *Your orders:*\n";
        $lines  = [$header];

        foreach ($orders as $order) {
            $statusIcon = match($order['order_status']) {
                'pending'    => '⏳',
                'processing' => '🔄',
                'completed'  => '✅',
                'cancelled'  => '❌',
                default      => '📋',
            };
            $payIcon = $order['payment_status'] === 'paid' ? '💳✅' : '💳⏳';
            $lines[] = "{$statusIcon} *{$order['order_number']}*";
            $lines[] = "   " . Currency::format((float)$order['total_amount'], $currency) . " · {$payIcon}";
            $lines[] = "   " . date('d/m/Y H:i', strtotime($order['created_at']));
            $lines[] = '';
        }

        return [
            'reply'         => implode("\n", $lines),
            'state'         => 'idle',
            'action_result' => $orders,
        ];
    }
}
