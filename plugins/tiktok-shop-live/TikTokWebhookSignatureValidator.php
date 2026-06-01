<?php

declare(strict_types=1);

final class TikTokWebhookSignatureValidator
{
    public function isValid(string $rawPayload, string $secret, ?string $signature): bool
    {
        if ($secret === '') {
            return true;
        }

        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $secret);
        return hash_equals($expected, $signature);
    }
}
