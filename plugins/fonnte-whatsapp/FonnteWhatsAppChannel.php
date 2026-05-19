<?php

declare(strict_types=1);

use App\Config\Database;
use App\Plugin\ChannelInterface;

class FonnteWhatsAppChannel implements ChannelInterface
{
    private const PLUGIN_SLUG = 'fonnte-whatsapp';

    private ?int $activeBranchId = null;

    public function getName(): string
    {
        return 'whatsapp';
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0) {
            return false;
        }

        return $this->getSetting($branchId, 'api_key') !== '';
    }

    public function parseMessage(array $payload): ?array
    {
        $from = trim((string)($payload['sender'] ?? ''));
        $text = trim((string)($payload['message'] ?? ''));

        if ($from === '' || $text === '') {
            return null;
        }

        return [
            'from'    => $from,
            'message' => $text,
        ];
    }

    public function sendMessage(string $recipient, string $message, array $options = []): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0) {
            return false;
        }

        $apiKey = $this->getSetting($branchId, 'api_key');
        if ($apiKey === '') {
            return false;
        }

        $ch = curl_init('https://api.fonnte.com/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ["Authorization: {$apiKey}"],
            CURLOPT_POSTFIELDS     => http_build_query([
                'target'  => $recipient,
                'message' => $message,
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log('[fonnte-whatsapp] send failed. HTTP ' . $httpCode . ': ' . ($error ?: (string)$response));
            return false;
        }

        $data = json_decode((string)$response, true);
        return ($data['status'] ?? false) === true;
    }

    public function isAvailable(int $branchId): bool
    {
        $this->activeBranchId = $branchId;

        return $this->getSetting($branchId, 'wa_number') !== ''
            && $this->getSetting($branchId, 'api_key') !== '';
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
            'wa_number' => 'wa_number',
            'api_key'   => 'api_key',
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
        $legacyStmt->execute([$branchId, 'FonnteProvider']);

        return (string)($legacyStmt->fetchColumn() ?: '');
    }
}
