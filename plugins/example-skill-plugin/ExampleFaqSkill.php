<?php

declare(strict_types=1);

use App\Skills\SkillInterface;

class ExampleFaqSkill implements SkillInterface
{
    public function canHandle(string $intent): bool
    {
        return $intent === 'faq_wifi';
    }

    public function handle(array $context): array
    {
        $lang = (string) ($context['language'] ?? 'id');

        $reply = $lang === 'en'
            ? "Our Wi-Fi is available for customers in-store. Please ask the cashier for today's password."
            : "Wi-Fi tersedia untuk customer yang dine-in. Silakan minta password hari ini ke kasir kami.";

        $convContext = (array) ($context['conv_context'] ?? []);
        $convContext['last_topic'] = 'faq_wifi';

        return [
            'reply'         => $reply,
            'state'         => 'idle',
            'action_result' => null,
            'conv_context'  => $convContext,
        ];
    }
}
