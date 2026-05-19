<?php

declare(strict_types=1);

use App\Config\Database;
use App\Plugin\ChannelInterface;

class BaileysWhatsAppChannel implements ChannelInterface
{
    private const PLUGIN_SLUG = 'baileys-whatsapp';

    private ?int $activeBranchId = null;

    public function getName(): string
    {
        return 'whatsapp_baileys';
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0) {
            return false;
        }

        $secretKey = $this->getSetting($branchId, 'secret_key');
        if ($secretKey === '') {
            return true;
        }

        $incoming = $this->headerValue($headers, 'X-Baileys-Token')
            ?: $this->headerValue($headers, 'X-Bridge-Token');

        return $incoming !== '' && hash_equals($secretKey, $incoming);
    }

    public function parseMessage(array $payload): ?array
    {
        $from = trim((string) ($payload['from'] ?? $payload['sender'] ?? ''));
        $message = trim((string) ($payload['message'] ?? $payload['text'] ?? ''));
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

        $outboundUrl = $this->getSetting($branchId, 'outbound_url');
        if ($outboundUrl === '') {
            return false;
        }

        $bridgeToken = $this->getSetting($branchId, 'bridge_token');
        $payload = [
            'to'      => $recipient,
            'message' => $message,
        ];

        $headers = ['Content-Type: application/json'];
        if ($bridgeToken !== '') {
            $headers[] = 'X-Bridge-Token: ' . $bridgeToken;
        }

        $ch = curl_init($outboundUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 12,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            error_log('[baileys-whatsapp] send failed. HTTP ' . $httpCode . ': ' . ($error ?: (string) $response));
            return false;
        }

        return true;
    }

    public function isAvailable(int $branchId): bool
    {
        $this->activeBranchId = $branchId;

        return $this->getSetting($branchId, 'wa_number') !== ''
            && $this->getSetting($branchId, 'outbound_url') !== '';
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
        $stmt = Database::getInstance()->prepare(
            'SELECT bws.* FROM branch_whatsapp_settings bws
             JOIN whatsapp_providers wp ON bws.provider_id = wp.id
             WHERE bws.branch_id = ? AND wp.adapter_class = ? AND bws.is_active = 1
             ORDER BY bws.id DESC
             LIMIT 1'
        );
        $stmt->execute([$branchId, 'BaileysBridgeProvider']);
        $row = $stmt->fetch();

        if (!$row) {
            return '';
        }

        if ($key === 'wa_number') {
            return (string) ($row['wa_number'] ?? '');
        }
        if ($key === 'bridge_token') {
            return (string) ($row['api_key'] ?? '');
        }
        if ($key === 'secret_key') {
            return (string) ($row['api_secret'] ?? '');
        }
        if ($key === 'outbound_url') {
            $extra = json_decode((string) ($row['extra_config'] ?? ''), true);
            return is_array($extra) ? (string) ($extra['outbound_url'] ?? '') : '';
        }

        return '';
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
