<?php

declare(strict_types=1);

namespace KopiBot\Domains\Chatbot;

class ChatMessageDTO
{
    public function __construct(
        public int $tenantId,
        public int $branchId,
        public string $channel,
        public string $senderId,
        public string $message,
        public ?int $customerId = null
    ) {}
}
