<?php

declare(strict_types=1);

use App\Config\Database;
use App\Plugin\ChannelInterface;

class VonageWhatsAppChannel implements ChannelInterface
{
    private const PLUGIN_SLUG = 'vonage-whatsapp';

    private ?int $activeBranchId = null;

    public function getName(): string
    {
        return 'whatsapp_vonage';
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0) {
            return false;
        }

        $apiSecret = $this->getSetting($branchId, 'api_secret');
        if ($apiSecret === '') {
            return true;
        }

        $header = $this->headerValue($headers, 'X-Nexmo-Signature')
            ?? $this->headerValue($headers, 'X-Vonage-Signature');

        if ($header === null || $header === '') {
            return true;
        }

        $received = preg_replace('/^sha256=/i', '', $header) ?? $header;
        $expected = hash_hmac('sha256', $rawBody, $apiSecret);

        return hash_equals($expected, $received);
    }

    public function parseMessage(array $payload): ?array
    {
        $channel = (string)($payload['channel'] ?? '');
        $msgType = (string)($payload['message_type'] ?? '');
        $from    = trim((string)($payload['from'] ?? ''));

        if ($channel !== 'whatsapp' || $from === '') {
            return null;
        }

        $message = $msgType === 'text' ? trim((string)($payload['text'] ?? '')) : '';
        if ($message === '') {
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

        $apiKey    = $this->getSetting($branchId, 'api_key');
        $apiSecret = $this->getSetting($branchId, 'api_secret');
        $waNumber  = $this->normalizeE164($this->getSetting($branchId, 'wa_number'));

        if ($apiKey === '' || $apiSecret === '' || $waNumber === '') {
            error_log('[vonage-whatsapp] Missing api_key, api_secret, or wa_number');
            return false;
        }

        $body = json_encode([
            'message_type' => 'text',
            'text'         => $message,
            'to'           => $this->normalizeE164($recipient),
            'from'         => $waNumber,
            'channel'      => 'whatsapp',
        ]);

        $credentials = base64_encode("{$apiKey}:{$apiSecret}");
        $ch = curl_init('https://api.nexmo.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Basic {$credentials}",
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT    => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 202) {
            error_log('[vonage-whatsapp] send failed. HTTP ' . $httpCode . ': ' . ($error ?: (string)$response));
            return false;
        }

        $data = json_decode((string)$response, true);
        return isset($data['message_uuid']) && $data['message_uuid'] !== '';
    }

    public function isAvailable(int $branchId): bool
    {
        $this->activeBranchId = $branchId;

        return $this->getSetting($branchId, 'wa_number') !== ''
            && $this->getSetting($branchId, 'api_key') !== ''
            && $this->getSetting($branchId, 'api_secret') !== '';
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
            return (string)$pluginValue;
        }

        $legacyFieldMap = [
            'wa_number'  => 'wa_number',
            'api_key'    => 'api_key',
            'api_secret' => 'api_secret',
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
        $legacyStmt->execute([$branchId, 'VonageProvider']);

        return (string)($legacyStmt->fetchColumn() ?: '');
    }

    private function normalizeE164(string $number): string
    {
        $number = preg_replace('/\D/', '', $number) ?? $number;
        return ltrim($number, '+');
    }

    private function headerValue(array $headers, string $target): ?string
    {
        foreach ($headers as $name => $value) {
            if (strcasecmp((string)$name, $target) === 0) {
                return is_array($value) ? (string)($value[0] ?? '') : (string)$value;
            }
        }

        return null;
    }
}
