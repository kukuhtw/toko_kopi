<?php

declare(strict_types=1);

namespace KopiBot\Domains\FAQ;

use KopiBot\Core\HookManager;

class FaqService
{
    public function __construct(
        private FaqRepository $repository = new FaqRepository()
    ) {}

    public function answer(int $tenantId, ?int $branchId, string $question, ?int $customerId = null): array
    {
        $results = $this->repository->searchKeyword($tenantId, $branchId, $question, 1);

        if (empty($results)) {
            $unansweredId = $this->repository->logUnanswered($tenantId, $branchId, $customerId, $question);
            $llmAnswer = $this->answerWithLlm($question);

            if ($llmAnswer !== '') {
                return [
                    'success' => true,
                    'answered' => true,
                    'source' => 'llm_fallback',
                    'message' => $llmAnswer,
                    'answer' => $llmAnswer,
                    'unanswered_id' => $unansweredId,
                ];
            }

            return [
                'success' => false,
                'answered' => false,
                'message' => 'Maaf, saya belum menemukan jawaban untuk pertanyaan tersebut.',
                'unanswered_id' => $unansweredId,
            ];
        }

        $faq = $results[0];

        return [
            'success' => true,
            'answered' => true,
            'source' => 'faq_keyword',
            'faq_id' => (int)$faq['id'],
            'question' => $faq['question'],
            'answer' => $faq['answer'],
        ];
    }

    private function answerWithLlm(string $question): string
    {
        $provider = $this->resolveProvider();
        if ($provider === null) {
            return '';
        }

        $systemPrompt = 'Anda adalah chatbot customer service toko. Jawab singkat, sopan, jelas, dan dalam Bahasa Indonesia. '
            . 'Jika informasi spesifik toko tidak tersedia, jangan mengarang kebijakan, harga, atau stok. '
            . 'Arahkan customer untuk bertanya ke admin jika perlu data spesifik.';

        try {
            if (method_exists($provider, 'completeWithSystemPrompt')) {
                return trim((string)($provider->completeWithSystemPrompt($question, $systemPrompt, 250) ?? ''));
            }

            if (method_exists($provider, 'chat')) {
                return trim((string)$provider->chat([
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $question],
                ], ['max_tokens' => 250]));
            }
        } catch (\Throwable $e) {
            error_log('[FaqService LLM fallback] ' . $e->getMessage());
        }

        return '';
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

    private function setting(string $key, string $default = ''): string
    {
        try {
            $stmt = \KopiBot\Core\DatabaseConnection::getInstance()->prepare(
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
