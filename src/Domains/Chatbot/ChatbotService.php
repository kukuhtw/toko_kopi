<?php

declare(strict_types=1);

namespace KopiBot\Domains\Chatbot;

use KopiBot\Domains\AI\ConversationContext;
use KopiBot\Domains\AI\ConversationMemoryService;

class ChatbotService
{
    public function __construct(
        private IntentDetector $intentDetector = new LlmIntentDetector(),
        private MessageRouter $router = new MessageRouter(),
        private ConversationMemoryService $memory = new ConversationMemoryService()
    ) {}

    public function process(ChatMessageDTO $message): array
    {
        $context = new ConversationContext(
            tenantId: $message->tenantId,
            branchId: $message->branchId,
            channel: $message->channel,
            senderId: $message->senderId,
            customerId: $message->customerId
        );

        $this->memory->rememberUserMessage($context, $message->message, [
            'channel' => $message->channel,
        ]);

        $intent = $this->intentDetector->detect($message->message);
        $response = $this->router->route($intent, $message);

        $this->memory->rememberAssistantMessage($context, (string)($response['message'] ?? ''), [
            'intent' => $intent,
            'response_type' => $response['type'] ?? 'text',
        ]);

        return array_merge($response, [
            'intent' => $intent,
            'tenant_id' => $message->tenantId,
            'branch_id' => $message->branchId,
            'channel' => $message->channel,
            'sender_id' => $message->senderId,
        ]);
    }
}
