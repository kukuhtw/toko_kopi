<?php

declare(strict_types=1);

namespace KopiBot\Channels\WhatsApp;

class OutgoingMessageDTO
{
    public function __construct(
        public string $phone,
        public string $message
    ) {}
}
