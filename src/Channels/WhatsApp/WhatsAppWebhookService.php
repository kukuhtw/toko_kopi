<?php

declare(strict_types=1);

namespace KopiBot\Channels\WhatsApp;

use KopiBot\Domains\Chatbot\ChatbotService;
use KopiBot\Domains\Chatbot\ChatMessageDTO;
use KopiBot\Domains\Customer\CustomerSessionResolver;

class WhatsAppWebhookService
{
    public function __construct(
        private ChatbotService $chatbot = new ChatbotService(),
        private WhatsAppGatewayInterface $gateway = new FonnteGateway(),
        private CustomerSessionResolver $customerResolver = new CustomerSessionResolver()
    ) {}

    public function handle(IncomingMessageDTO $incoming, bool $sendReply = true): array
    {
        $customer = $this->customerResolver->resolveFromWhatsApp(
            tenantId: $incoming->tenantId,
            branchId: $incoming->branchId,
            phone: $incoming->phone
        );

        $chatbotResult = $this->chatbot->process(new ChatMessageDTO(
            tenantId: $incoming->tenantId,
            branchId: $incoming->branchId,
            channel: 'whatsapp',
            senderId: $incoming->senderId,
            message: $incoming->message,
            customerId: (int) ($customer['customer_id'] ?? 0)
        ));

        $replyText = (string) ($chatbotResult['message'] ?? 'Maaf, saya belum bisa menjawab pesan tersebut.');
        $sendResult = null;

        if ($sendReply) {
            $sendResult = $this->gateway->sendMessage(new OutgoingMessageDTO(
                phone: $incoming->phone,
                message: $replyText
            ));
        }

        return [
            'success' => true,
            'customer' => $customer,
            'reply' => $replyText,
            'chatbot' => $chatbotResult,
            'send_result' => $sendResult,
        ];
    }
}
