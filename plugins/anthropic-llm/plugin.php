<?php

declare(strict_types=1);

require_once __DIR__ . '/AnthropicProvider.php';
require_once __DIR__ . '/AnthropicIntentDetector.php';
require_once __DIR__ . '/AnthropicLlmPlugin.php';

return [
    'class'       => AnthropicLlmPlugin::class,
    'name'        => 'Anthropic LLM',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Intent detector berbasis Anthropic Claude dengan prompt caching dan native tool use untuk akurasi dan efisiensi biaya lebih tinggi.',
    'requires'    => '1.0.0',
];
