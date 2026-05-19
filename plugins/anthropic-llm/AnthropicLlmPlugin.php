<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};

/**
 * Plugin yang mendaftarkan Anthropic Claude sebagai LLM provider.
 *
 * Cara mengaktifkan:
 *   1. Set "anthropic-llm": {"active": true} di plugins/plugins.json
 *   2. Pilih provider "Anthropic" dan model di Dashboard → Settings
 *   3. Masukkan API key di kolom "API Key" pada halaman Settings
 *
 * API key dibaca dari app_settings.llm_api_key oleh ChatbotEngine,
 * lalu diteruskan langsung ke plugin — tidak ada pembacaan DB kedua.
 */
class AnthropicLlmPlugin implements PluginInterface
{
    public function getName(): string    { return 'Anthropic LLM'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'Toko Kopi'; }

    public function register(): void
    {
        HookManager::addFilter('llm.providers', [$this, 'registerProvider'], 10, 3);
    }

    /**
     * @param array  $providers Map provider yang sudah terdaftar
     * @param string $provider  Provider yang dipilih di app_settings
     * @param string $model     Model yang dipilih di app_settings
     * @param string $apiKey    API key dari app_settings.llm_api_key (diteruskan oleh ChatbotEngine)
     */
    public function registerProvider(array $providers, string $provider, string $model, string $apiKey = ''): array
    {
        if ($provider !== 'anthropic') {
            return $providers;
        }

        if ($apiKey === '') {
            error_log('[AnthropicLlmPlugin] API key kosong — isi di Dashboard → Settings.');
            return $providers;
        }

        $model = $model ?: 'claude-haiku-4-5-20251001';

        $anthropicProvider      = new AnthropicProvider($apiKey, $model);
        $providers['anthropic'] = new AnthropicIntentDetector($anthropicProvider);

        return $providers;
    }
}
