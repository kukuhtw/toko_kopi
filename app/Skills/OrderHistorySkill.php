<?php

declare(strict_types=1);

namespace App\Skills;

use App\Config\Database;
use App\Helpers\Currency;

class OrderHistorySkill implements SkillInterface
{
    public function canHandle(string $intent): bool
    {
        return $intent === 'tanya_status_order';
    }

    public function handle(array $ctx): array
    {
        $convCtx = $ctx['conv_context'] ?? [];
        $message = mb_strtolower($ctx['message'], 'UTF-8');
        $explicitOrderNumber = $this->extractOrderNumber($ctx['message'] ?? '');

        if ($explicitOrderNumber !== '') {
            return $this->showOrderDetail($explicitOrderNumber, $ctx, $convCtx);
        }

        if (!empty($convCtx['last_orders']) && preg_match('/\bdetail\b/iu', $message)) {
            return $this->showOrderDetail($convCtx['last_orders'][0], $ctx, $convCtx);
        }

        return $this->showOrderList($ctx, $convCtx);
    }

    private function showOrderList(array $ctx, array $convCtx): array
    {
        $lang     = $ctx['language'] ?? 'id';
        $currency = $ctx['currency'] ?? 'IDR';
        $db       = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT o.id, o.order_number, o.total_amount, o.order_status, o.payment_status, o.created_at,
                    GROUP_CONCAT(
                        CONCAT(
                            oi.menu_name,
                            CASE
                                WHEN oi.variant_label IS NOT NULL AND oi.variant_label != "" THEN CONCAT(" - ", oi.variant_label)
                                ELSE ""
                            END,
                            " x",
                            oi.quantity
                        )
                        ORDER BY oi.id SEPARATOR ", "
                    ) AS items_summary
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.id
             WHERE o.customer_id = ? AND o.branch_id = ?
             GROUP BY o.id
             ORDER BY o.created_at DESC
             LIMIT 3'
        );
        $stmt->execute([$ctx['customer']['id'], $ctx['branch_id']]);
        $orders = $stmt->fetchAll();

        if (empty($orders)) {
            $reply = $lang === 'id'
                ? 'Kamu belum punya riwayat order di cabang ini. Ketik *menu* untuk mulai memesan.'
                : 'You have no order history at this branch. Type *menu* to start ordering.';
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $header = $lang === 'id' ? "📦 *Order kamu:*\n" : "📦 *Your orders:*\n";
        $lines  = [$header];

        foreach ($orders as $order) {
            $payIcon = $this->payLabel($order['payment_status'], $lang);

            $lines[] = "▪️ *{$order['order_number']}*";
            $lines[] = Currency::format((float)$order['total_amount'], $currency)
                     . ' · ' . $this->statusEmoji($order['order_status'])
                     . ' · ' . $payIcon;
            if ($order['items_summary']) {
                $lines[] = $order['items_summary'];
            }
            $lines[] = date('d/m/Y H:i', strtotime($order['created_at']));
            $lines[] = '';
        }

        $lines[] = $lang === 'id'
            ? 'Ketik *detail* untuk melihat detail order terbaru.'
            : 'Type *detail* to see the latest order detail.';

        $newCtx = array_merge($convCtx, [
            'last_orders' => array_column($orders, 'order_number'),
        ]);

        return [
            'reply'         => implode("\n", $lines),
            'state'         => 'idle',
            'action_result' => $orders,
            'conv_context'  => array_merge($newCtx, ['last_topic' => 'order_history']),
        ];
    }

    private function showOrderDetail(string $orderNumber, array $ctx, array $convCtx): array
    {
        $lang     = $ctx['language'] ?? 'id';
        $currency = $ctx['currency'] ?? 'IDR';
        $db       = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT o.order_number, o.total_amount, o.discount_amount, o.order_status,
                    o.payment_status, o.created_at, o.customer_name, o.customer_address,
                    o.notes, oi.menu_name, oi.variant_label, oi.quantity, oi.unit_price
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.order_number = ? AND o.customer_id = ?
             ORDER BY oi.id'
        );
        $stmt->execute([$orderNumber, $ctx['customer']['id']]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            $reply = $lang === 'id' ? 'Detail order tidak ditemukan.' : 'Order detail not found.';
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $o     = $rows[0];
        $lines = [];

        $payLabel   = $lang === 'id' ? 'Pembayaran' : 'Payment';
        $itemsLabel = $lang === 'id' ? '*Item:*' : '*Items:*';

        $lines[] = "📋 *{$o['order_number']}*";
        $lines[] = 'Status: ' . $this->statusEmoji($o['order_status']) . ' ' . ucfirst($o['order_status']);
        $lines[] = "{$payLabel}: " . $this->payLabel($o['payment_status'], $lang);
        $lines[] = date('d/m/Y H:i', strtotime($o['created_at']));
        $lines[] = '';
        $lines[] = $itemsLabel;

        foreach ($rows as $row) {
            $subtotal = (float)$row['unit_price'] * (int)$row['quantity'];
            $displayName = (string)$row['menu_name'];
            if (!empty($row['variant_label'])) {
                $displayName .= ' - ' . $row['variant_label'];
            }
            $lines[] = "• {$displayName} x{$row['quantity']} — " . Currency::format($subtotal, $currency);
        }

        $lines[] = '';

        if ((float)$o['discount_amount'] > 0) {
            $discLabel = $lang === 'id' ? 'Diskon' : 'Discount';
            $lines[] = "{$discLabel}: -" . Currency::format((float)$o['discount_amount'], $currency);
        }

        $lines[] = '*Total: ' . Currency::format((float)$o['total_amount'], $currency) . '*';

        if (!empty($o['customer_address'])) {
            $lines[] = '';
            $lines[] = '📍 ' . $o['customer_address'];
        }
        if (!empty($o['notes'])) {
            $notesLabel = $lang === 'id' ? 'Catatan' : 'Notes';
            $lines[] = "📝 {$notesLabel}: " . $o['notes'];
        }

        return [
            'reply'         => implode("\n", $lines),
            'state'         => 'idle',
            'action_result' => $rows,
            'conv_context'  => array_merge($convCtx, ['last_topic' => 'order_history']),
        ];
    }

    private function extractOrderNumber(string $message): string
    {
        if (preg_match('/\b(ORD-\d{8}-[A-Z0-9]+)\b/i', $message, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return '';
    }

    private function statusEmoji(string $status): string
    {
        return match ($status) {
            'pending'    => '⏳',
            'confirmed'  => '✅',
            'processing' => '🔄',
            'ready'      => '🎉',
            'delivered'  => '📬',
            'cancelled'  => '❌',
            default      => '📋',
        };
    }

    private function payLabel(string $status, string $lang): string
    {
        if ($status === 'paid') {
            return $lang === 'id' ? '✅ Lunas' : '✅ Paid';
        }
        return $lang === 'id' ? '⏳ Belum bayar' : '⏳ Unpaid';
    }
}
