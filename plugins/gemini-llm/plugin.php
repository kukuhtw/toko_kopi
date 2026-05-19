<?php

declare(strict_types=1);

require_once __DIR__ . '/GeminiProvider.php';
require_once __DIR__ . '/GeminiIntentDetector.php';
require_once __DIR__ . '/GeminiLlmPlugin.php';

return [
    'class'       => GeminiLlmPlugin::class,
    'name'        => 'Gemini LLM',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Intent detector berbasis Google Gemini untuk klasifikasi intent dan ekstraksi item order.',
    'requires'    => '1.0.0',
];
