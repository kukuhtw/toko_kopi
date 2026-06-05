<?php

declare(strict_types=1);

namespace KopiBot\Channels\WhatsApp;

class IncomingMessageDTO
{
    public function __construct(
        public int $tenantId,
        public int $branchId,
        public string $senderId,
        public string $phone,
        public string $message,
        public string $rawPayload = ''
    ) {}
}
