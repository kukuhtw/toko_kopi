<?php

declare(strict_types=1);

namespace KopiBot\Domains\Order;

use KopiBot\Contracts\SkillInterface;

class CheckoutSkill implements SkillInterface
{
    public function __construct(
        private CheckoutService $checkoutService = new CheckoutService()
    ) {}

    public function canHandle(string $intent): bool
    {
        return in_array($intent, ['checkout', 'create_checkout', 'confirm_order'], true);
    }

    public function handle(array $context): array
    {
        $branchId = (int)($context['branch_id'] ?? 0);
        $senderId = (string)($context['sender_id'] ?? '');
        $customerId = (int)($context['customer_id'] ?? 0);
        $channel = (string)($context['channel'] ?? 'web');
        $message = strtolower(trim((string)($context['message'] ?? '')));

        if ($branchId <= 0 || $senderId === '') {
            return [
                'success' => false,
                'reply' => 'Data checkout belum lengkap. Silakan ulangi dari chat atau halaman order.',
            ];
        }

        $sessionKey = hash('sha256', "{$channel}:{$branchId}:{$senderId}");

        $input = [
            'branch_id' => $branchId,
            'session_id' => $senderId,
            'name' => (string)($context['customer_name'] ?? 'Customer'),
            'email' => (string)($context['customer_email'] ?? ''),
            'whatsapp' => (string)($context['customer_whatsapp'] ?? $senderId),
            'fulfillment_type' => $this->detectFulfillmentType($message),
            'address' => (string)($context['address'] ?? ''),
            'postal_code' => (string)($context['postal_code'] ?? ''),
            'table_number' => (string)($context['table_number'] ?? ''),
            'promo_code' => (string)($context['promo_code'] ?? ''),
        ];

        if ($customerId <= 0) {
            return [
                'success' => false,
                'reply' => 'Untuk checkout, saya perlu data pelanggan terlebih dahulu. Silakan isi nama dan nomor WhatsApp di chat.',
                'state' => 'awaiting_customer_identity',
            ];
        }

        $result = $this->checkoutService->checkoutSession($branchId, $sessionKey, $senderId, $input, $channel);
        if (empty($result['success'])) {
            return [
                'success' => false,
                'reply' => (string)($result['message'] ?? 'Checkout belum berhasil.'),
                'state' => 'checkout_failed',
                'action_result' => $result,
            ];
        }

        $data = (array)($result['data'] ?? []);
        $orderNumber = (string)($data['order_number'] ?? $data['number'] ?? '');
        $paymentUrl = (string)($data['payment']['url'] ?? $data['payment']['checkout_url'] ?? '');

        $reply = $orderNumber !== ''
            ? 'Order berhasil dibuat. Nomor order: ' . $orderNumber . '.'
            : 'Order berhasil dibuat.';

        if ($paymentUrl !== '') {
            $reply .= "\nSilakan lanjut pembayaran melalui link berikut: " . $paymentUrl;
        }

        return [
            'success' => true,
            'reply' => $reply,
            'state' => 'order_created',
            'action_result' => $result,
        ];
    }

    private function detectFulfillmentType(string $message): string
    {
        if (str_contains($message, 'meja') || str_contains($message, 'table')) {
            return 'table';
        }

        if (str_contains($message, 'pickup') || str_contains($message, 'ambil')) {
            return 'pickup';
        }

        if (str_contains($message, 'delivery') || str_contains($message, 'antar') || str_contains($message, 'alamat')) {
            return 'delivery';
        }

        return 'pickup';
    }
}
