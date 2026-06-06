<?php

declare(strict_types=1);

namespace KopiBot\Contracts;

interface ChannelInterface
{
    public function getName(): string;

    public function verifyWebhook(array $headers, string $rawBody): bool;

    public function parseMessage(array $payload): ?array;

    public function sendMessage(string $recipient, string $message, array $options = []): bool;

    public function isAvailable(int $branchId): bool;
}
