<?php

declare(strict_types=1);

namespace App\WhatsAppProviders;

interface WhatsAppProviderInterface
{
    /**
     * Send a text message via this provider.
     */
    public function sendMessage(string $to, string $message): bool;

    /**
     * Parse incoming webhook payload and return normalized message data.
     *
     * @return array|null {from, message, raw} or null if not a valid message
     */
    public function parseWebhook(array $payload): ?array;

    /**
     * Verify webhook signature / token (provider-specific).
     */
    public function verifyWebhook(array $headers, string $rawBody, array $payload = [], array $server = []): bool;

    public function getName(): string;
}
