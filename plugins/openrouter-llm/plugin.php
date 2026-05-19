<?php

declare(strict_types=1);

require_once __DIR__ . '/OpenRouterProvider.php';
require_once __DIR__ . '/OpenRouterIntentDetector.php';
require_once __DIR__ . '/OpenRouterLlmPlugin.php';

return [
    'class'       => OpenRouterLlmPlugin::class,
    'name'        => 'OpenRouter LLM',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Intent detector via OpenRouter — satu API key untuk 200+ model (GPT, Claude, Gemini, Llama, DeepSeek, Mistral, dll).',
    'requires'    => '1.0.0',
];
