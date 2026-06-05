<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

class ConversationMemoryService
{
    public function __construct(
        private ConversationMemoryRepository $repository = new ConversationMemoryRepository()
    ) {}

    public function rememberUserMessage(ConversationContext $context, string $message, array $meta = []): array
    {
        $sessionId = $this->repository->findOrCreateSession($context);
        $messageId = $this->repository->addMessage($context->tenantId, $sessionId, 'user', $message, $meta);

        return [
            'session_id' => $sessionId,
            'message_id' => $messageId,
        ];
    }

    public function rememberAssistantMessage(ConversationContext $context, string $message, array $meta = []): array
    {
        $sessionId = $this->repository->findOrCreateSession($context);
        $messageId = $this->repository->addMessage($context->tenantId, $sessionId, 'assistant', $message, $meta);

        return [
            'session_id' => $sessionId,
            'message_id' => $messageId,
        ];
    }

    public function recent(ConversationContext $context, int $limit = 10): array
    {
        $sessionId = $this->repository->findOrCreateSession($context);

        return [
            'session_id' => $sessionId,
            'messages' => $this->repository->recentMessages($context->tenantId, $sessionId, $limit),
        ];
    }
}
