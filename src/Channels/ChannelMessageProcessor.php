<?php

declare(strict_types=1);

namespace KopiBot\Channels;

use KopiBot\Contracts\ChannelInterface;
use KopiBot\Domains\Chatbot\ChatbotService;
use KopiBot\Domains\Chatbot\ChatMessageDTO;
use KopiBot\Domains\Customer\CustomerDTO;
use KopiBot\Domains\Customer\CustomerService;

class ChannelMessageProcessor
{
    public function __construct(
        private ChatbotService $chatbot = new ChatbotService(),
        private CustomerService $customerService = new CustomerService()
    ) {}

    public function process(
        ChannelInterface $channel,
        string $channelName,
        int $tenantId,
        int $branchId,
        string $senderId,
        string $message,
        bool $sendReply = true
    ): array {
        $customer = $this->resolveCustomer($channelName, $tenantId, $branchId, $senderId);

        $chatbotResult = $this->chatbot->process(new ChatMessageDTO(
            tenantId: $tenantId,
            branchId: $branchId,
            channel: $channelName,
            senderId: $senderId,
            message: $message,
            customerId: (int)($customer['customer_id'] ?? 0)
        ));

        $replyText = trim((string)($chatbotResult['message'] ?? ''));
        $sendResult = null;
        if ($sendReply && $replyText !== '') {
            $sendResult = $channel->sendMessage($senderId, $replyText);
        }

        return [
            'success' => true,
            'customer' => $customer,
            'reply' => $replyText,
            'chatbot' => $chatbotResult,
            'send_result' => $sendResult,
            'intent' => $chatbotResult['intent'] ?? null,
        ];
    }

    private function resolveCustomer(string $channelName, int $tenantId, int $branchId, string $senderId): array
    {
        $isWhatsApp = str_contains($channelName, 'whatsapp') || $channelName === 'wa';
        return $this->customerService->findOrCreate(new CustomerDTO(
            tenantId: $tenantId,
            branchId: $branchId,
            name: ucfirst($channelName) . ' Customer ' . $senderId,
            phone: $isWhatsApp ? $senderId : null,
            whatsapp: $isWhatsApp ? $senderId : null,
            source: $channelName
        ));
    }
}
