<?php

declare(strict_types=1);

namespace KopiBot\Channels\WhatsApp;

use KopiBot\Domains\Chatbot\ChatbotService;
use KopiBot\Domains\Chatbot\ChatMessageDTO;

class WhatsAppWebhookService
{
    public function __construct(
        private ChatbotService $chatbot = new ChatbotService(),
        private WhatsAppGatewayInterface $gateway = new FonnteGateway()
    ) {}

    public function handle(IncomingMessageDTO $incoming, bool $sendReply = true): array
    {
        $chatbotResult = $this->chatbot->process(new ChatMessageDTO(
            tenantId: $incoming->tenantId,
            branchId: $incoming->branchId,
            channel: 'whatsapp',
            senderId: $incoming->senderId,
            message: $incoming->message,
            customerId: null
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
            'reply' => $replyText,
            'chatbot' => $chatbotResult,
            'send_result' => $sendResult,
        ];
    }
}
