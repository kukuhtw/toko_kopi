<?php

declare(strict_types=1);

use KopiBot\Contracts\ChannelInterface;
use KopiBot\Core\DatabaseConnection;

class MetaWhatsAppChannel implements ChannelInterface
{
    private const PLUGIN_SLUG = 'meta-whatsapp';

    private ?int $activeBranchId = null;

    public function getName(): string
    {
        return 'whatsapp_meta';
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0) {
            return false;
        }

        $appSecret = $this->getSetting($branchId, 'api_secret');
        if ($appSecret === '') {
            return true;
        }

        $signature = $this->headerValue($headers, 'X-Hub-Signature-256');
        if ($signature === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        return hash_equals($expected, $signature);
    }

    public function parseMessage(array $payload): ?array
    {
        $entry = $payload['entry'][0] ?? null;
        if (!$entry) {
            return null;
        }

        $changes = $entry['changes'][0] ?? null;
        if (!$changes || ($changes['field'] ?? '') !== 'messages') {
            return null;
        }

        $value = $changes['value'] ?? [];
        $msgData = $value['messages'][0] ?? null;
        if (!$msgData || ($msgData['type'] ?? '') !== 'text') {
            return null;
        }

        return [
            'from' => (string) ($msgData['from'] ?? ''),
            'message' => (string) ($msgData['text']['body'] ?? ''),
        ];
    }

    public function sendMessage(string $recipient, string $message, array $options = []): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0) {
            return false;
        }

        $accessToken = $this->getSetting($branchId, 'api_key');
        $phoneNumberId = $this->getSetting($branchId, 'phone_number_id');
        if ($accessToken === '' || $phoneNumberId === '') {
            error_log('[meta-whatsapp] Missing api_key or phone_number_id for branch ' . $branchId);
            return false;
        }

        $to = preg_replace('/[^0-9]/', '', $recipient) ?? '';
        if ($to === '') {
            return false;
        }

        $url = 'https://graph.facebook.com/v19.0/' . rawurlencode($phoneNumberId) . '/messages';
        $body = json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 12,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !in_array($httpCode, [200, 201], true)) {
            error_log('[meta-whatsapp] send failed. HTTP ' . $httpCode . ': ' . ($error ?: (string) $response));
            return false;
        }

        $data = json_decode((string) $response, true);
        return !empty($data['messages'][0]['id']);
    }

    public function isAvailable(int $branchId): bool
    {
        $this->activeBranchId = $branchId;

        return $this->getSetting($branchId, 'api_key') !== ''
            && $this->getSetting($branchId, 'webhook_token') !== '';
    }

    public function handleVerification(array $query, int $branchId): ?string
    {
        if (($query['hub_mode'] ?? '') !== 'subscribe') {
            return null;
        }

        $token = (string) ($query['hub_verify_token'] ?? '');
        $challenge = (string) ($query['hub_challenge'] ?? '');
        if ($branchId <= 0 || $token === '' || $challenge === '') {
            return null;
        }

        $expectedToken = $this->getSetting($branchId, 'webhook_token');
        if ($expectedToken === '' || !hash_equals($expectedToken, $token)) {
            return null;
        }

        return $challenge;
    }

    public function resolveBranchId(array $headers, array $payload, string $rawBody, array $query = []): ?int
    {
        $branchId = isset($query['branch']) ? (int) $query['branch'] : 0;
        if ($branchId > 0) {
            return $branchId;
        }

        $verifyToken = (string) ($query['hub_verify_token'] ?? '');
        if ($verifyToken !== '') {
            $stmt = DatabaseConnection::getInstance()->prepare(
                'SELECT branch_id
                 FROM plugin_branch_settings
                 WHERE plugin_slug = ? AND setting_key = ? AND setting_val = ?
                 LIMIT 1'
            );
            $stmt->execute([self::PLUGIN_SLUG, 'webhook_token', $verifyToken]);
            $resolved = (int) ($stmt->fetchColumn() ?: 0);
            if ($resolved > 0) {
                return $resolved;
            }
        }

        $stmt = DatabaseConnection::getInstance()->prepare(
            'SELECT bws.branch_id
             FROM branch_whatsapp_settings bws
             JOIN whatsapp_providers wp ON bws.provider_id = wp.id
             WHERE wp.adapter_class = ? AND bws.is_active = 1
             ORDER BY bws.id DESC
             LIMIT 1'
        );
        $stmt->execute(['MetaCloudApiProvider']);
        $resolved = (int) ($stmt->fetchColumn() ?: 0);

        return $resolved > 0 ? $resolved : null;
    }

    private function getSetting(int $branchId, string $key): string
    {
        $pluginStmt = DatabaseConnection::getInstance()->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1'
        );
        $pluginStmt->execute([self::PLUGIN_SLUG, $branchId, $key]);
        $pluginValue = $pluginStmt->fetchColumn();
        if ($pluginValue !== false && $pluginValue !== null && $pluginValue !== '') {
            return (string) $pluginValue;
        }

        $legacyFieldMap = [
            'wa_number' => 'wa_number',
            'api_key' => 'api_key',
            'api_secret' => 'api_secret',
            'webhook_token' => 'webhook_token',
        ];
        $legacyColumn = $legacyFieldMap[$key] ?? null;
        if ($legacyColumn === null) {
            return '';
        }

        $legacyStmt = DatabaseConnection::getInstance()->prepare(
            'SELECT bws.' . $legacyColumn . '
             FROM branch_whatsapp_settings bws
             JOIN whatsapp_providers wp ON bws.provider_id = wp.id
             WHERE bws.branch_id = ? AND wp.adapter_class = ? AND bws.is_active = 1
             ORDER BY bws.id DESC
             LIMIT 1'
        );
        $legacyStmt->execute([$branchId, 'MetaCloudApiProvider']);

        return (string) ($legacyStmt->fetchColumn() ?: '');
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
