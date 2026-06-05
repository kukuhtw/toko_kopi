<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

class ConversationContext
{
    public function __construct(
        public int $tenantId,
        public int $branchId,
        public string $channel,
        public string $senderId,
        public ?int $customerId = null
    ) {}

    public function sessionKey(): string
    {
        return $this->channel . ':' . $this->senderId;
    }
}
