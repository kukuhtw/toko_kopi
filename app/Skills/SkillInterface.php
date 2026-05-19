<?php

declare(strict_types=1);

namespace App\Skills;

interface SkillInterface
{
    /**
     * @param  array $context {branch_id, customer, conversation, cart, intent, message, language, currency}
     * @return array {reply, state, action_result}
     */
    public function handle(array $context): array;

    public function canHandle(string $intent): bool;
}
