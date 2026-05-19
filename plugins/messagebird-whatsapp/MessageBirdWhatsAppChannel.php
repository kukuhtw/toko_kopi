<?php

declare(strict_types=1);

use App\Config\Database;
use App\Plugin\ChannelInterface;

class MessageBirdWhatsAppChannel implements ChannelInterface
{
    private const PLUGIN_SLUG = 'messagebird-whatsapp';

    private ?int $activeBranchId = null;

    public function getName(): string
    {
        return 'whatsapp_messagebird';
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0) {
            return false;
        }

        $signingKey = $this->getSetting($branchId, 'api_secret');
        if ($signingKey === '') {
            return true;
        }

        $signature = $this->headerValue($headers, 'MessageBird-Signature');
        $timestamp = $this->headerValue($headers, 'MessageBird-Request-Timestamp');

        if ($signature === '' || $timestamp === '') {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $url = $this->buildRequestUrl($_SERVER);
        if ($url === '') {
            return false;
        }

        $bodyHash = hash('sha256', $rawBody);
        $signPayload = "{$timestamp}\n{$url}\n{$bodyHash}";
        $expected = hash_hmac('sha256', $signPayload, $signingKey);

        return hash_equals($expected, $signature);
    }

    public function parseMessage(array $payload): ?array
    {
        $type = (string) ($payload['type'] ?? '');
        $direction = (string) ($payload['message']['direction'] ?? '');

        if ($type !== 'message.created' || $direction !== 'received') {
            return null;
        }

        $from = (string) ($payload['message']['source'] ?? '');
        $msgType = (string) ($payload['message']['type'] ?? 'text');
        $message = '';

        if ($msgType === 'text') {
            $message = (string) ($payload['message']['content']['text'] ?? '');
        }

        if ($from === '' || $message === '') {
            return null;
        }

        return [
            'from'    => $from,
            'message' => $message,
        ];
    }

    public function sendMessage(string $recipient, string $message, array $options = []): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0) {
            return false;
        }

        $accessKey = $this->getSetting($branchId, 'api_key');
        $channelId = $this->getSetting($branchId, 'channel_id');
        if ($accessKey === '' || $channelId === '') {
            error_log('[messagebird-whatsapp] missing accessKey or channelId');
            return false;
        }

        $body = json_encode([
            'to'      => $this->normalizeE164($recipient),
            'from'    => $channelId,
            'type'    => 'text',
            'content' => ['text' => $message],
        ]);

        $ch = curl_init('https://conversations.messagebird.com/v1/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: AccessKey {$accessKey}",
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT    => 12,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || !in_array($httpCode, [200, 201], true)) {
            error_log('[messagebird-whatsapp] send failed. HTTP ' . $httpCode . ': ' . ($error ?: (string) $response));
            return false;
        }

        $data = json_decode((string) $response, true);
        return isset($data['id']) && $data['id'] !== '';
    }

    public function isAvailable(int $branchId): bool
    {
        $this->activeBranchId = $branchId;

        return $this->getSetting($branchId, 'api_key') !== ''
            && $this->getSetting($branchId, 'channel_id') !== '';
    }

    private function getSetting(int $branchId, string $key): string
    {
        $pluginStmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1'
        );
        $pluginStmt->execute([self::PLUGIN_SLUG, $branchId, $key]);
        $pluginValue = $pluginStmt->fetchColumn();
        if ($pluginValue !== false && $pluginValue !== null && $pluginValue !== '') {
            return (string) $pluginValue;
        }

        return $this->getLegacySetting($branchId, $key);
    }

    private function getLegacySetting(int $branchId, string $key): string
    {
        $legacyFieldMap = [
            'api_key'    => 'api_key',
            'api_secret' => 'api_secret',
            'channel_id' => 'wa_number',
        ];
        $legacyColumn = $legacyFieldMap[$key] ?? null;
        if ($legacyColumn === null) {
            return '';
        }

        $legacyStmt = Database::getInstance()->prepare(
            'SELECT bws.' . $legacyColumn . '
             FROM branch_whatsapp_settings bws
             JOIN whatsapp_providers wp ON bws.provider_id = wp.id
             WHERE bws.branch_id = ? AND wp.adapter_class = ? AND bws.is_active = 1
             ORDER BY bws.id DESC
             LIMIT 1'
        );
        $legacyStmt->execute([$branchId, 'MessageBirdProvider']);

        return (string) ($legacyStmt->fetchColumn() ?: '');
    }

    private function normalizeE164(string $number): string
    {
        $number = preg_replace('/\D/', '', $number) ?? $number;
        return '+' . ltrim($number, '+');
    }

    private function buildRequestUrl(array $server): string
    {
        $https  = strtolower((string) ($server['HTTPS'] ?? ''));
        $scheme = ($https === 'on' || $https === '1') ? 'https' : 'http';
        $host   = (string) ($server['HTTP_HOST'] ?? '');
        $uri    = (string) ($server['REQUEST_URI'] ?? '');

        return ($host !== '' && $uri !== '') ? "{$scheme}://{$host}{$uri}" : '';
    }

    private function headerValue(array $headers, string $target): string
    {
        foreach ($headers as $name => $value) {
            if (strcasecmp((string) $name, $target) === 0) {
                return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
            }
        }

        return '';
    }
}
