<?php

declare(strict_types=1);

use App\Plugin\ChannelInterface;
use App\Config\Database;

class InstagramDmChannel implements ChannelInterface
{
    private const PLUGIN_SLUG = 'instagram-dm';
    private ?int $activeBranchId = null;

    public function getName(): string
    {
        return 'instagram';
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0) {
            return false;
        }

        $appSecret = $this->getSetting($branchId, 'app_secret');
        if ($appSecret === '') {
            return false;
        }

        $signature = $this->header($headers, 'X-Hub-Signature-256');
        if ($signature === '' || !str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        return hash_equals($expected, $signature);
    }

    public function parseMessage(array $payload): ?array
    {
        foreach ((array)($payload['entry'] ?? []) as $entry) {
            foreach ((array)($entry['messaging'] ?? []) as $event) {
                if (!empty($event['message']['is_echo'])) {
                    continue;
                }

                $from = trim((string)($event['sender']['id'] ?? ''));
                $text = trim((string)($event['message']['text'] ?? ''));

                if ($from !== '' && $text !== '') {
                    return [
                        'from'    => $from,
                        'message' => $text,
                    ];
                }
            }
        }

        return null;
    }

    public function sendMessage(string $recipient, string $message, array $options = []): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0) {
            return false;
        }

        $accessToken = $this->getSetting($branchId, 'access_token');
        $businessId  = $this->getSetting($branchId, 'instagram_business_id');
        $graphVer    = $this->getSetting($branchId, 'graph_version') ?: 'v20.0';

        if ($accessToken === '' || $businessId === '') {
            return false;
        }

        $url = 'https://graph.facebook.com/' . rawurlencode($graphVer) . '/'
             . rawurlencode($businessId) . '/messages?access_token=' . rawurlencode($accessToken);

        $payload = [
            'recipient'      => ['id' => $recipient],
            'messaging_type' => 'RESPONSE',
            'message'        => ['text' => $message],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOSIGNAL       => 1,
            CURLOPT_CONNECTTIMEOUT_MS => 5000,
            CURLOPT_TIMEOUT_MS        => 15000,
        ]);

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $response === false) {
            error_log('[instagram-dm] sendMessage cURL error: ' . $err);
            return false;
        }

        if ($code < 200 || $code >= 300) {
            error_log('[instagram-dm] sendMessage HTTP ' . $code . ': ' . $response);
            return false;
        }

        return true;
    }

    public function isAvailable(int $branchId): bool
    {
        $this->activeBranchId = $branchId;

        return $this->getSetting($branchId, 'access_token') !== ''
            && $this->getSetting($branchId, 'app_secret') !== ''
            && $this->getSetting($branchId, 'verify_token') !== ''
            && $this->getSetting($branchId, 'instagram_business_id') !== '';
    }

    public function handleVerification(array $query, int $branchId): ?string
    {
        $mode        = (string)($query['hub_mode'] ?? $query['hub.mode'] ?? '');
        $verifyToken = (string)($query['hub_verify_token'] ?? $query['hub.verify_token'] ?? '');
        $challenge   = (string)($query['hub_challenge'] ?? $query['hub.challenge'] ?? '');

        if ($mode !== 'subscribe' || $challenge === '') {
            return null;
        }

        $savedToken = $this->getSetting($branchId, 'verify_token');
        if ($savedToken === '' || !hash_equals($savedToken, $verifyToken)) {
            return null;
        }

        $this->activeBranchId = $branchId;
        return $challenge;
    }

    private function getSetting(int $branchId, string $key): string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $key]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    private function header(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string)$key, $name) === 0) {
                return (string)$value;
            }
        }
        return '';
    }
}
