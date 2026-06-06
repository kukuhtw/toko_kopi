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
        private ConversationMemoryService $memory = new ConversationMemoryService(),
        private MultiIntentPlanner $planner = new MultiIntentPlanner()
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

        $plan = $this->planner->plan($message);
        if ($plan !== []) {
            $response = $this->executePlan($message, $plan);

            $this->memory->rememberAssistantMessage($context, (string)($response['message'] ?? ''), [
                'intent' => 'multi_task',
                'response_type' => $response['type'] ?? 'text',
                'task_count' => count($plan),
            ]);

            return array_merge($response, [
                'intent' => 'multi_task',
                'tenant_id' => $message->tenantId,
                'branch_id' => $message->branchId,
                'channel' => $message->channel,
                'sender_id' => $message->senderId,
            ]);
        }

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

    private function executePlan(ChatMessageDTO $message, array $plan): array
    {
        $messages = [];
        $results = [];

        foreach ($plan as $index => $task) {
            $intent = (string)($task['intent'] ?? IntentType::UNKNOWN);
            $taskMessage = trim((string)($task['message'] ?? $message->message));
            if ($intent === IntentType::UNKNOWN || $taskMessage === '') {
                continue;
            }

            $taskDto = new ChatMessageDTO(
                tenantId: $message->tenantId,
                branchId: $message->branchId,
                channel: $message->channel,
                senderId: $message->senderId,
                message: $taskMessage,
                customerId: $message->customerId
            );

            $result = $this->router->route($intent, $taskDto);
            $results[] = array_merge($result, [
                'intent' => $intent,
                'task_message' => $taskMessage,
                'task_index' => $index,
            ]);

            $text = trim((string)($result['message'] ?? ''));
            if ($text !== '') {
                $messages[] = $text;
            }
        }

        if ($messages === []) {
            return $this->router->route(IntentType::UNKNOWN, $message);
        }

        return [
            'success' => true,
            'type' => 'multi_task',
            'message' => implode("\n\n", $messages),
            'tasks' => $results,
        ];
    }
}
