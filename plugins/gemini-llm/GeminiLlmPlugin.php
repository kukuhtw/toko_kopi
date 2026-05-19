<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

/**
 * Plugin yang mendaftarkan Google Gemini sebagai LLM provider.
 */
class GeminiLlmPlugin implements PluginInterface
{
    public function getName(): string    { return 'Gemini LLM'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'Toko Kopi'; }

    public function register(): void
    {
        HookManager::addFilter('llm.providers', [$this, 'registerProvider'], 10, 3);
        HookManager::addFilter('llm.model_list', [$this, 'addModelList'], 10, 2);
    }

    public function registerProvider(array $providers, string $provider, string $model): array
    {
        if ($provider !== 'gemini') {
            return $providers;
        }

        $apiKey = $this->resolveApiKey();
        if ($apiKey === '') {
            error_log('[GeminiLlmPlugin] API key tidak ditemukan - plugin tidak aktif.');
            return $providers;
        }

        $model = $model ?: 'gemini-2.0-flash';
        $geminiProvider = new GeminiProvider($apiKey, $model);
        $providers['gemini'] = new GeminiIntentDetector($geminiProvider);

        return $providers;
    }

    public function addModelList(array $list, string $provider): array
    {
        if ($provider !== 'gemini') {
            return $list;
        }

        return array_merge($list, [
            ['value' => 'gemini-2.5-flash', 'label' => 'Gemini 2.5 Flash'],
            ['value' => 'gemini-2.5-flash-lite', 'label' => 'Gemini 2.5 Flash Lite'],
            ['value' => 'gemini-2.0-flash', 'label' => 'Gemini 2.0 Flash (Recommended)'],
            ['value' => 'gemini-1.5-flash', 'label' => 'Gemini 1.5 Flash'],
            ['value' => 'gemini-1.5-pro', 'label' => 'Gemini 1.5 Pro'],
        ]);
    }

    private function resolveApiKey(): string
    {
        $env = getenv('GEMINI_API_KEY');
        if ($env !== false && $env !== '') {
            return $env;
        }

        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT setting_val FROM app_settings WHERE setting_key = ? LIMIT 1'
            );
            $stmt->execute(['llm_api_key']);
            return (string) ($stmt->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
