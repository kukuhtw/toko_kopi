<?php

declare(strict_types=1);

namespace KopiBot\Domains\Chatbot;

class ChatbotService
{
    public function __construct(
        private IntentDetector $intentDetector = new IntentDetector(),
        private MessageRouter $router = new MessageRouter()
    ) {}

    public function process(ChatMessageDTO $message): array
    {
        $intent = $this->intentDetector->detect($message->message);
        $response = $this->router->route($intent, $message);

        return array_merge($response, [
            'intent' => $intent,
            'tenant_id' => $message->tenantId,
            'branch_id' => $message->branchId,
            'channel' => $message->channel,
            'sender_id' => $message->senderId,
        ]);
    }
}
