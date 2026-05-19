<?php

declare(strict_types=1);

use App\Config\Database;
use App\Plugin\ChannelInterface;

class TwilioWhatsAppChannel implements ChannelInterface
{
    private const PLUGIN_SLUG = 'twilio-whatsapp';

    private ?int $activeBranchId = null;

    public function getName(): string
    {
        return 'whatsapp_twilio';
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0) {
            return false;
        }

        $authToken = $this->getSetting($branchId, 'api_secret');
        if ($authToken === '') {
            return true;
        }

        $signature = $this->headerValue($headers, 'X-Twilio-Signature');
        if ($signature === '') {
            return false;
        }

        $url = $this->buildRequestUrl($_SERVER);
        if ($url === '') {
            return false;
        }

        $payload = [];
        parse_str($rawBody, $payload);
        if ($payload === [] && !empty($_POST)) {
            $payload = $_POST;
        }

        ksort($payload);
        $data = $url;
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $nestedValue) {
                    $data .= $key . (string) $nestedValue;
                }
                continue;
            }

            $data .= $key . (string) $value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));
        return hash_equals($expected, $signature);
    }

    public function parseMessage(array $payload): ?array
    {
        $from = trim((string) ($payload['From'] ?? ''));
        $text = trim((string) ($payload['Body'] ?? ''));

        if ($from === '' || $text === '') {
            return null;
        }

        return [
            'from'    => $this->stripWhatsAppPrefix($from),
            'message' => $text,
        ];
    }

    public function sendMessage(string $recipient, string $message, array $options = []): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0) {
            return false;
        }

        $accountSid  = $this->getSetting($branchId, 'api_key');
        $authToken   = $this->getSetting($branchId, 'api_secret');
        $fromAddress = $this->normalizeWhatsAppAddress($this->getSetting($branchId, 'wa_number'));

        if ($accountSid === '' || $authToken === '' || $fromAddress === '') {
            return false;
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($accountSid) . '/Messages.json';
        $postFields = http_build_query([
            'From' => $fromAddress,
            'To'   => $this->normalizeWhatsAppAddress($recipient),
            'Body' => $message,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => $accountSid . ':' . $authToken,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_TIMEOUT        => 12,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || !in_array($httpCode, [200, 201], true)) {
            error_log('[twilio-whatsapp] send failed. HTTP ' . $httpCode . ': ' . ($error ?: (string) $response));
            return false;
        }

        $data = json_decode((string) $response, true);
        return isset($data['sid']) && $data['sid'] !== '';
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
            return (string) $pluginValue;
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
        $legacyStmt->execute([$branchId, 'TwilioProvider']);

        return (string) ($legacyStmt->fetchColumn() ?: '');
    }

    private function normalizeWhatsAppAddress(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return str_starts_with($value, 'whatsapp:') ? $value : 'whatsapp:' . $value;
    }

    private function stripWhatsAppPrefix(string $value): string
    {
        return preg_replace('/^whatsapp:/i', '', $value) ?? $value;
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
