<?php

declare(strict_types=1);

namespace App\Plugin;

interface LlmProviderInterface
{
    /** Identifier unik provider, contoh: 'gemini', 'groq', 'mistral' */
    public function getName(): string;

    /**
     * Kirim pesan ke LLM dan kembalikan teks response.
     *
     * @param array $messages  Format OpenAI: [['role'=>'user','content'=>'...']]
     * @param array $options   Parameter tambahan (temperature, max_tokens, dll.)
     */
    public function chat(array $messages, array $options = []): string;

    /** Estimasi biaya dalam USD berdasarkan jumlah token */
    public function estimateCost(int $promptTokens, int $completionTokens): float;

    /** Cek apakah provider siap digunakan (API key terkonfigurasi, dll.) */
    public function isAvailable(): bool;
}
