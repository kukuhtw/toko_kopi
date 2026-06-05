<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

class ConversationOptionResolver
{
    public function __construct(
        private ConversationStateRepository $stateRepository = new ConversationStateRepository()
    ) {}

    public function waitForProductOption(ConversationContext $context, array $options): void
    {
        $this->stateRepository->setState($context, ConversationState::WAITING_FOR_PRODUCT_OPTION, [
            'options' => array_values($options),
        ]);
    }

    public function resolveSelection(ConversationContext $context, string $message): ?array
    {
        $state = $this->stateRepository->getState($context);

        if (!$state || $state['state'] !== ConversationState::WAITING_FOR_PRODUCT_OPTION) {
            return null;
        }

        if (!preg_match('/^\d+$/', trim($message))) {
            return null;
        }

        $index = (int) trim($message) - 1;
        $payload = json_decode((string) ($state['payload_json'] ?? '{}'), true) ?: [];
        $options = $payload['options'] ?? [];

        if (!isset($options[$index])) {
            return null;
        }

        return [
            'intent' => 'selected_product_option',
            'product' => $options[$index],
        ];
    }
}
