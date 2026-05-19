<?php

declare(strict_types=1);

use App\Config\Database;
use App\Plugin\ChannelInterface;
use App\Services\WhatsAppSharedInboxService;

class DiscordChannel implements ChannelInterface
{
    private const PLUGIN_SLUG = 'discord-channel';

    private ?int $activeBranchId = null;
    private string $interactionToken = '';
    private string $applicationId = '';
    private bool $sawUnsupportedCommand = false;

    public function getName(): string
    {
        return 'discord';
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $branchId = $this->activeBranchId ?? 0;
        if ($branchId <= 0 || !function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        $publicKey = $this->getSetting($branchId, 'api_secret', true);
        if ($publicKey === '') {
            return false;
        }

        $signature = $this->header($headers, 'X-Signature-Ed25519');
        $timestamp = $this->header($headers, 'X-Signature-Timestamp');
        if ($signature === '' || $timestamp === '') {
            return false;
        }

        $sigBytes = @hex2bin($signature);
        $keyBytes = @hex2bin($publicKey);
        if ($sigBytes === false || $keyBytes === false) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($sigBytes, $timestamp . $rawBody, $keyBytes);
    }

    public function parseMessage(array $payload): ?array
    {
        $this->sawUnsupportedCommand = false;

        if ((int) ($payload['type'] ?? 0) !== 2) {
            return null;
        }

        $commandName = mb_strtolower((string) ($payload['data']['name'] ?? ''), 'UTF-8');
        if (!in_array($commandName, ['chat', 'kopibot', 'menu', 'promo'], true)) {
            $this->sawUnsupportedCommand = true;
            return null;
        }

        $message = $this->extractCommandMessage($payload, $commandName);
        $userId = (string) ($payload['member']['user']['id'] ?? $payload['user']['id'] ?? '');
        $this->interactionToken = (string) ($payload['token'] ?? '');
        $this->applicationId = (string) ($payload['application_id'] ?? $this->getSetting($this->activeBranchId ?? 0, 'bot_identifier', true));

        if ($userId === '' || $message === '') {
            $this->sawUnsupportedCommand = true;
            return null;
        }

        return [
            'from'    => $userId,
            'message' => $message,
        ];
    }

    public function sendMessage(string $recipient, string $message, array $options = []): bool
    {
        $applicationId = $this->applicationId !== '' ? $this->applicationId : $this->getSetting($this->activeBranchId ?? 0, 'bot_identifier', true);
        if ($this->interactionToken === '' || $applicationId === '') {
            return false;
        }

        $url = 'https://discord.com/api/v10/webhooks/' . $applicationId . '/' . $this->interactionToken;
        $payload = ['content' => $message];

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

        if ($response === false || !in_array($httpCode, [200, 204], true)) {
            error_log('[discord-channel] follow-up send failed. HTTP ' . $httpCode . ': ' . (string) $response);
            return false;
        }

        return true;
    }

    public function isAvailable(int $branchId): bool
    {
        $this->activeBranchId = $branchId;

        return $this->getSetting($branchId, 'api_secret', true) !== ''
            && $this->getSetting($branchId, 'is_active', true) === '1';
    }

    public function resolveBranchId(array $headers, array $payload, string $rawBody, array $query): ?int
    {
        $queryBranchId = (int) ($query['branch'] ?? 0);
        if ($queryBranchId > 0) {
            return $queryBranchId;
        }

        $publicKey = $this->resolvePublicKeyFromHeaders($headers, $rawBody);
        if ($publicKey === '') {
            return null;
        }

        $stmt = Database::getInstance()->prepare(
            'SELECT branch_id FROM plugin_branch_settings
             WHERE plugin_slug = ? AND setting_key = ? AND setting_val = ?
             LIMIT 1'
        );
        $stmt->execute([self::PLUGIN_SLUG, 'api_secret', $publicKey]);
        $branchId = $stmt->fetchColumn();
        if ($branchId !== false && $branchId !== null) {
            return (int) $branchId;
        }

        $legacyStmt = Database::getInstance()->prepare(
            'SELECT branch_id FROM branch_bot_settings
             WHERE platform = ? AND api_secret = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $legacyStmt->execute(['discord', $publicKey]);
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
            'discord',
            $transportBranchId,
            $customerIdentifier,
            $message,
            'discord_shared_inbox_enabled'
        );
    }

    public function respondToWebhook(array $payload, ?string $reply = null, string $phase = 'success'): ?array
    {
        if ($phase === 'verified' && (int) ($payload['type'] ?? 0) === 1) {
            return ['type' => 1];
        }

        if ($phase === 'no_message' && $this->sawUnsupportedCommand) {
            return $this->buildInteractionResponse(
                'Perintah belum didukung. Gunakan /chat dengan opsi pesan, atau /menu dan /promo.',
                true
            );
        }

        if ($phase === 'success') {
            return $this->buildInteractionResponse($reply !== null && $reply !== '' ? $reply : 'Tidak ada balasan dari chatbot.');
        }

        return null;
    }

    private function buildInteractionResponse(string $message, bool $ephemeral = false): array
    {
        return [
            'type' => 4,
            'data' => [
                'content' => $message,
                'flags'   => $ephemeral ? 64 : 0,
            ],
        ];
    }

    private function extractCommandMessage(array $payload, string $commandName): string
    {
        if ($commandName === 'menu') {
            return 'menu';
        }

        if ($commandName === 'promo') {
            return 'promo';
        }

        $options = $payload['data']['options'] ?? [];
        if (!is_array($options)) {
            return '';
        }

        $stack = $options;
        while ($stack !== []) {
            $option = array_shift($stack);
            if (!is_array($option)) {
                continue;
            }

            $name = mb_strtolower((string) ($option['name'] ?? ''), 'UTF-8');
            if (in_array($name, ['message', 'text', 'prompt', 'query'], true) && isset($option['value'])) {
                return trim((string) $option['value']);
            }

            if (isset($option['value']) && is_string($option['value']) && trim($option['value']) !== '') {
                return trim($option['value']);
            }

            if (!empty($option['options']) && is_array($option['options'])) {
                foreach ($option['options'] as $child) {
                    $stack[] = $child;
                }
            }
        }

        return '';
    }

    private function resolvePublicKeyFromHeaders(array $headers, string $rawBody): string
    {
        $signature = $this->header($headers, 'X-Signature-Ed25519');
        $timestamp = $this->header($headers, 'X-Signature-Timestamp');
        if ($signature === '' || $timestamp === '' || !function_exists('sodium_crypto_sign_verify_detached')) {
            return '';
        }

        $sigBytes = @hex2bin($signature);
        if ($sigBytes === false) {
            return '';
        }

        $pluginStmt = Database::getInstance()->prepare(
            'SELECT branch_id, setting_val
             FROM plugin_branch_settings
             WHERE plugin_slug = ? AND setting_key = ?'
        );
        $pluginStmt->execute([self::PLUGIN_SLUG, 'api_secret']);
        $pluginKeys = $pluginStmt->fetchAll();

        foreach ($pluginKeys as $row) {
            $candidate = (string) ($row['setting_val'] ?? '');
            $keyBytes = @hex2bin($candidate);
            if ($candidate !== '' && $keyBytes !== false && sodium_crypto_sign_verify_detached($sigBytes, $timestamp . $rawBody, $keyBytes)) {
                return $candidate;
            }
        }

        $legacyStmt = Database::getInstance()->query(
            "SELECT api_secret FROM branch_bot_settings WHERE platform = 'discord' AND api_secret IS NOT NULL AND api_secret <> ''"
        );
        $legacyKeys = $legacyStmt ? $legacyStmt->fetchAll() : [];
        foreach ($legacyKeys as $row) {
            $candidate = (string) ($row['api_secret'] ?? '');
            $keyBytes = @hex2bin($candidate);
            if ($candidate !== '' && $keyBytes !== false && sodium_crypto_sign_verify_detached($sigBytes, $timestamp . $rawBody, $keyBytes)) {
                return $candidate;
            }
        }

        return '';
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
            'bot_identifier' => 'bot_identifier',
            'api_key'        => 'api_key',
            'api_secret'     => 'api_secret',
            'is_active'      => 'is_active',
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
        $legacyStmt->execute([$branchId, 'discord']);

        return (string) ($legacyStmt->fetchColumn() ?: '');
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
