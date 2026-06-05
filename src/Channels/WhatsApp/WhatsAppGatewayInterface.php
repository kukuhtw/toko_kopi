<?php

declare(strict_types=1);

namespace KopiBot\Channels\WhatsApp;

interface WhatsAppGatewayInterface
{
    public function sendMessage(OutgoingMessageDTO $message): array;
}
