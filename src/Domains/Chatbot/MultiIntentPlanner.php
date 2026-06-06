<?php

declare(strict_types=1);

namespace KopiBot\Domains\Chatbot;

class MultiIntentPlanner
{
    public function __construct(
        private IntentDetector $intentDetector = new LlmIntentDetector()
    ) {}

    public function plan(ChatMessageDTO $message): array
    {
        $parts = $this->splitMessage($message->message);
        if (count($parts) <= 1) {
            return [];
        }

        $items = [];
        foreach ($parts as $part) {
            $intent = $this->intentDetector->detect($part);
            if ($intent === IntentType::UNKNOWN) {
                continue;
            }

            $items[] = [
                'intent' => $intent,
                'message' => $part,
            ];
        }

        return count($items) > 1 ? $items : [];
    }

    private function splitMessage(string $message): array
    {
        $message = trim($message);
        if ($message === '') {
            return [];
        }

        $normalized = preg_replace('/\s+/', ' ', $message) ?? $message;
        $parts = preg_split('/\s*(?:,|;| lalu | kemudian | terus | dan juga | setelah itu | sekalian )\s*/iu', $normalized) ?: [];

        return array_values(array_filter(array_map(
            static fn(string $part): string => trim($part),
            $parts
        )));
    }
}
