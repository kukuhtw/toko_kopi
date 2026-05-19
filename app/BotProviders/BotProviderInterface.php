<?php

declare(strict_types=1);

namespace App\BotProviders;

interface BotProviderInterface
{
    public function getName(): string;

    public function sendMessage(string $to, string $message, array $context = []): bool;

    public function parseWebhook(array $payload): ?array;

    public function verifyWebhook(array $headers, string $rawBody): bool;
}
