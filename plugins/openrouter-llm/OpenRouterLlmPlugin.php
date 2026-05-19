<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

/**
 * Plugin yang mendaftarkan OpenRouter sebagai LLM provider.
 *
 * Cara mengaktifkan:
 *   1. Set "openrouter-llm": {"active": true} di plugins/plugins.json
 *   2. Pilih provider "OpenRouter" dan model di Dashboard → Settings
 *   3. Masukkan API key dari openrouter.ai/keys (atau set OPENROUTER_API_KEY di .env)
 */
class OpenRouterLlmPlugin implements PluginInterface
{
    public function getName(): string    { return 'OpenRouter LLM'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'Toko Kopi'; }

    public function register(): void
    {
        HookManager::addFilter('llm.providers',  [$this, 'registerProvider'], 10, 3);
        HookManager::addFilter('llm.model_list', [$this, 'addModelList'],     10, 2);
    }

    /**
     * @param array  $providers Map provider yang sudah terdaftar
     * @param string $provider  Provider yang dipilih di app_settings
     * @param string $model     Model yang dipilih di app_settings
     */
    public function registerProvider(array $providers, string $provider, string $model, string $apiKey = ''): array
    {
        if ($provider !== 'openrouter') {
            return $providers;
        }

        $apiKey = trim($apiKey) !== '' ? $apiKey : $this->resolveApiKey();
        if ($apiKey === '') {
            error_log('[OpenRouterLlmPlugin] API key tidak ditemukan — plugin tidak aktif.');
            return $providers;
        }

        $model = $model ?: 'openai/gpt-4o-mini';

        $orProvider = new OpenRouterProvider(
            apiKey:   $apiKey,
            model:    $model,
            siteUrl:  defined('BASE_URL') ? BASE_URL : '',
            siteName: 'Toko Kopi Chatbot'
        );

        $providers['openrouter'] = new OpenRouterIntentDetector($orProvider);

        return $providers;
    }

    /**
     * Menambahkan daftar model OpenRouter ke filter model_list
     * (digunakan jika dashboard membaca model secara dinamis via hook).
     */
    public function addModelList(array $list, string $provider): array
    {
        if ($provider !== 'openrouter') {
            return $list;
        }

        return array_merge($list, [
            ['value' => 'openai/gpt-4o-mini',                  'label' => 'GPT-4o Mini (Recommended)'],
            ['value' => 'openai/gpt-4.1-mini',                 'label' => 'GPT-4.1 Mini'],
            ['value' => 'openai/gpt-4.1-nano',                 'label' => 'GPT-4.1 Nano (Fastest)'],
            ['value' => 'openai/gpt-4o',                       'label' => 'GPT-4o'],
            ['value' => 'openai/gpt-4.1',                      'label' => 'GPT-4.1'],
            ['value' => 'anthropic/claude-haiku-4-5',          'label' => 'Claude Haiku 4.5'],
            ['value' => 'anthropic/claude-sonnet-4-5',         'label' => 'Claude Sonnet 4.5'],
            ['value' => 'google/gemini-2.0-flash-001',         'label' => 'Gemini 2.0 Flash (Murah)'],
            ['value' => 'google/gemini-flash-1.5',             'label' => 'Gemini 1.5 Flash'],
            ['value' => 'meta-llama/llama-3.3-70b-instruct',   'label' => 'Llama 3.3 70B'],
            ['value' => 'meta-llama/llama-3.1-8b-instruct',    'label' => 'Llama 3.1 8B (Ultra-murah)'],
            ['value' => 'deepseek/deepseek-chat',              'label' => 'DeepSeek V3 (Sangat murah)'],
            ['value' => 'mistralai/mistral-7b-instruct',       'label' => 'Mistral 7B (Ultra-murah)'],
            ['value' => 'qwen/qwen-2.5-72b-instruct',          'label' => 'Qwen 2.5 72B'],
        ]);
    }

    private function resolveApiKey(): string
    {
        // Prioritas 1: environment variable
        $env = getenv('OPENROUTER_API_KEY');
        if ($env !== false && $env !== '') {
            return $env;
        }

        // Prioritas 2: llm_api_key di app_settings
        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT setting_val FROM app_settings WHERE setting_key = ? LIMIT 1'
            );
            $stmt->execute(['llm_api_key']);
            return (string) ($stmt->fetchColumn() ?: '');
        } catch (\Throwable) {
            return '';
        }
    }
}
