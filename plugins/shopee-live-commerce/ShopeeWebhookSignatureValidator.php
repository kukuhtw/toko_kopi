<?php

declare(strict_types=1);

final class ShopeeWebhookSignatureValidator
{
    public function isValid(string $rawPayload, string $secret, ?string $signature): bool
    {
        if ($secret === '') {
            return true;
        }

        if ($signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawPayload, $secret), $signature);
    }
}
