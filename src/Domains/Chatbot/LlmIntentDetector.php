<?php

declare(strict_types=1);

namespace KopiBot\Domains\Chatbot;

use KopiBot\Core\HookManager;

class LlmIntentDetector extends IntentDetector
{
    public function __construct(
        private IntentDetector $fallback = new IntentDetector()
    ) {}

    public function detect(string $message): string
    {
        $provider = $this->resolveProvider();
        if ($provider === null) {
            return $this->fallback->detect($message);
        }

        try {
            $raw = $this->callProvider($provider, $message);
            $intent = $this->normalizeIntent($raw);
            if ($intent !== null) {
                return $intent;
            }
        } catch (\Throwable $e) {
            error_log('[LlmIntentDetector] ' . $e->getMessage());
        }

        return $this->fallback->detect($message);
    }

    private function resolveProvider(): ?object
    {
        $providerName = $this->setting('llm_provider', getenv('LLM_PROVIDER') ?: 'openrouter');
        $model = $this->setting('llm_model', getenv('LLM_MODEL') ?: '');
        $apiKey = $this->setting('llm_api_key', getenv('OPENROUTER_API_KEY') ?: '');

        $providers = HookManager::applyFilters('llm.providers', [], $providerName, $model, $apiKey);
        $provider = $providers[$providerName] ?? null;

        return is_object($provider) ? $provider : null;
    }

    private function callProvider(object $provider, string $message): string
    {
        $systemPrompt = 'Classify the Indonesian commerce chatbot message into exactly one intent. '
            . 'Allowed intents: show_menu, product_search, ask_faq, apply_promo, create_order, check_order_status, talk_to_human, unknown. '
            . 'Return only the intent name.';

        if (method_exists($provider, 'completeWithSystemPrompt')) {
            return (string)($provider->completeWithSystemPrompt($message, $systemPrompt, 20) ?? '');
        }

        if (method_exists($provider, 'chat')) {
            return (string)$provider->chat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $message],
            ], ['max_tokens' => 20]);
        }

        if (method_exists($provider, 'detect')) {
            return (string)$provider->detect($message);
        }

        return '';
    }

    private function normalizeIntent(string $raw): ?string
    {
        $value = strtolower(trim($raw));
        $value = preg_replace('/[^a-z_]+/', '', $value) ?? $value;

        $legacyMap = [
            'tanya_menu' => IntentType::SHOW_MENU,
            'tanya_harga' => IntentType::PRODUCT_SEARCH,
            'tanya_promo' => IntentType::APPLY_PROMO,
            'tambah_item' => IntentType::CREATE_ORDER,
            'checkout' => IntentType::CREATE_ORDER,
            'lihat_cart' => IntentType::CREATE_ORDER,
            'tanya_status_order' => IntentType::CHECK_ORDER_STATUS,
            'small_talk' => IntentType::ASK_FAQ,
            'out_of_scope' => IntentType::UNKNOWN,
        ];

        $value = $legacyMap[$value] ?? $value;

        $allowed = [
            IntentType::SHOW_MENU,
            IntentType::PRODUCT_SEARCH,
            IntentType::ASK_FAQ,
            IntentType::APPLY_PROMO,
            IntentType::CREATE_ORDER,
            IntentType::CHECK_ORDER_STATUS,
            IntentType::TALK_TO_HUMAN,
            IntentType::UNKNOWN,
        ];

        return in_array($value, $allowed, true) ? $value : null;
    }

    private function setting(string $key, string $default = ''): string
    {
        try {
            $dbClass = '\\KopiBot\\Core\\DatabaseConnection';
            if (!class_exists($dbClass)) {
                return $default;
            }

            $stmt = $dbClass::getInstance()->prepare(
                'SELECT setting_val FROM app_settings WHERE setting_key = ? LIMIT 1'
            );
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();

            return $value === false || $value === null || $value === '' ? $default : (string)$value;
        } catch (\Throwable) {
            return $default;
        }
    }
}
