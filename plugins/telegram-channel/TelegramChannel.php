<?php

declare(strict_types=1);

use App\Config\Database;
use App\Plugin\ChannelInterface;
use App\Services\WhatsAppSharedInboxService;

class TelegramChannel implements ChannelInterface
{
    private const PLUGIN_SLUG = 'telegram-channel';

    private ?int $activeBranchId = null;

    public function getName(): string
    {
        return 'telegram';
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0) {
            return false;
        }

        $botToken = $this->getSetting($branchId, 'api_key', true);
        if ($botToken === '') {
            return false;
        }

        $secretToken = $this->getSetting($branchId, 'webhook_token', true);
        if ($secretToken === '') {
            return true;
        }

        $incoming = $this->header($headers, 'X-Telegram-Bot-Api-Secret-Token');
        return $incoming !== '' && hash_equals($secretToken, $incoming);
    }

    public function parseMessage(array $payload): ?array
    {
        $messageNode = $payload['message'] ?? $payload['edited_message'] ?? $payload['channel_post'] ?? null;
        if (!is_array($messageNode)) {
            return null;
        }

        $text = trim((string)($messageNode['text'] ?? ''));
        $chatId = (string)($messageNode['chat']['id'] ?? $messageNode['from']['id'] ?? '');

        if ($chatId === '' || $text === '') {
            return null;
        }

        return [
            'from'    => $chatId,
            'message' => $text,
        ];
    }

    public function sendMessage(string $recipient, string $message, array $options = []): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0) {
            return false;
        }

        $botToken = $this->getSetting($branchId, 'api_key', true);
        if ($botToken === '') {
            return false;
        }

        $url = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';
        $payload = [
            'chat_id' => $recipient,
            'text'    => $message,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 12,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log('[telegram-channel] send failed. HTTP ' . $httpCode . ': ' . (string) $response);
            return false;
        }

        $data = json_decode((string) $response, true);
        return (bool) ($data['ok'] ?? false);
    }

    public function isAvailable(int $branchId): bool
    {
        $this->activeBranchId = $branchId;

        return $this->getSetting($branchId, 'api_key', true) !== ''
            && $this->getSetting($branchId, 'is_active', true) === '1';
    }

    public function resolveBranchId(array $headers, array $payload, string $rawBody, array $query): ?int
    {
        $queryBranchId = (int) ($query['branch'] ?? 0);
        if ($queryBranchId > 0) {
            return $queryBranchId;
        }

        $secretToken = $this->header($headers, 'X-Telegram-Bot-Api-Secret-Token');
        if ($secretToken === '') {
            return null;
        }

        $stmt = Database::getInstance()->prepare(
            'SELECT branch_id FROM plugin_branch_settings
             WHERE plugin_slug = ? AND setting_key = ? AND setting_val = ?
             LIMIT 1'
        );
        $stmt->execute([self::PLUGIN_SLUG, 'webhook_token', $secretToken]);
        $branchId = $stmt->fetchColumn();
        if ($branchId !== false && $branchId !== null) {
            return (int) $branchId;
        }

        $legacyStmt = Database::getInstance()->prepare(
            'SELECT branch_id FROM branch_bot_settings
             WHERE platform = ? AND webhook_token = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $legacyStmt->execute(['telegram', $secretToken]);
        $legacyBranchId = $legacyStmt->fetchColumn();
        if ($legacyBranchId !== false && $legacyBranchId !== null) {
            return (int) $legacyBranchId;
        }

        return null;
    }

    public function resolveBusinessBranch(int $transportBranchId, string $customerIdentifier, string $message): array
    {
        $sharedInbox = new WhatsAppSharedInboxService();
        return $sharedInbox->resolveBranch(
            'telegram',
            $transportBranchId,
            $customerIdentifier,
            $message,
            'telegram_shared_inbox_enabled'
        );
    }

    private function getSetting(int $branchId, string $key, bool $allowLegacy = false): string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $key]);
        $pluginValue = $stmt->fetchColumn();
        if ($pluginValue !== false && $pluginValue !== null && $pluginValue !== '') {
            return (string) $pluginValue;
        }

        if (!$allowLegacy) {
            return '';
        }

        $legacyFieldMap = [
            'bot_username'  => 'bot_identifier',
            'api_key'       => 'api_key',
            'webhook_token' => 'webhook_token',
            'is_active'     => 'is_active',
        ];
        $legacyColumn = $legacyFieldMap[$key] ?? null;
        if ($legacyColumn === null) {
            return '';
        }

        $legacyStmt = Database::getInstance()->prepare(
            'SELECT ' . $legacyColumn . '
             FROM branch_bot_settings
             WHERE branch_id = ? AND platform = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $legacyStmt->execute([$branchId, 'telegram']);

        $legacyValue = $legacyStmt->fetchColumn();
        if ($legacyValue === false || $legacyValue === null) {
            return '';
        }

        return (string) $legacyValue;
    }

    private function header(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
            }
        }

        return '';
    }
}
