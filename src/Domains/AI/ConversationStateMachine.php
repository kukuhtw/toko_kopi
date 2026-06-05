<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

class ConversationStateMachine
{
    public function __construct(
        private ConversationStateRepository $repository = new ConversationStateRepository()
    ) {}

    public function current(ConversationContext $context): array
    {
        $state = $this->repository->getState($context);

        if (!$state) {
            return [
                'state' => ConversationState::IDLE,
                'payload' => [],
            ];
        }

        return [
            'state' => $state['state'],
            'payload' => json_decode((string) ($state['payload_json'] ?? '{}'), true) ?: [],
        ];
    }

    public function waitForQty(ConversationContext $context, string $productName): void
    {
        $this->repository->setState($context, ConversationState::WAITING_FOR_QTY, [
            'product_name' => $productName,
        ]);
    }

    public function clear(ConversationContext $context): void
    {
        $this->repository->clearState($context);
    }

    public function resolveShortReply(ConversationContext $context, string $message): ?array
    {
        $current = $this->current($context);

        if ($current['state'] === ConversationState::WAITING_FOR_QTY && preg_match('/^\d+$/', trim($message))) {
            return [
                'intent' => 'add_to_cart',
                'product_name' => $current['payload']['product_name'] ?? '',
                'qty' => (int) trim($message),
            ];
        }

        return null;
    }
}
