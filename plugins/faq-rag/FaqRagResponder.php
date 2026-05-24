<?php

declare(strict_types=1);

use App\Config\Database;

final class FaqRagResponder
{
    private FaqRepository $faqs;
    private string $provider = 'none';
    private string $apiKey = '';
    private string $model = '';

    public function __construct(?FaqRepository $faqs = null)
    {
        $this->faqs = $faqs ?? new FaqRepository();
        $this->loadConfig();
    }

    public function detectAsFaq(string $message, int $branchId): bool
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');
        if ($lower === '') {
            return false;
        }

        $hasFaqCue = preg_match('/\?|jam|buka|tutup|wifi|wi-fi|alamat|parkir|halal|reservasi|booking|kontak|telepon|delivery|takeaway|take away|dine in|bayar|payment|cashless|qris/u', $lower) === 1;
        if (!$hasFaqCue && preg_match('/\b(pesan|order|checkout|promo|diskon|voucher|keranjang|cart|komplain|refund)\b/u', $lower) === 1) {
            return false;
        }

        $matches = $this->faqs->searchRelevant($message, $branchId, 1, 0.40);
        if (empty($matches)) {
            return false;
        }

        $topScore = (float)($matches[0]['_score'] ?? 0.0);
        return $topScore >= 0.58 || ($hasFaqCue && $topScore >= 0.44);
    }

    public function answer(string $message, int $branchId, string $language = 'id'): ?array
    {
        $matches = $this->faqs->searchRelevant($message, $branchId, 3, 0.34);
        if (empty($matches)) {
            return null;
        }

        $reply = $this->composeReply($message, $matches, $language);
        return [
            'reply' => $reply,
            'matches' => $matches,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $matches
     */
    public function composeReply(string $message, array $matches, string $language): string
    {
        if ($this->provider !== 'none' && $this->apiKey !== '') {
            $reply = $this->composeWithLlm($message, $matches, $language);
            if ($reply !== null && trim($reply) !== '') {
                return trim($reply);
            }
        }

        $top = $matches[0];
        $answer = trim((string)($top['answer'] ?? ''));
        $question = trim((string)($top['question'] ?? ''));

        if (count($matches) === 1) {
            return $answer;
        }

        $lines = [$answer];
        $lines[] = $language === 'en'
            ? 'Related FAQs you might need:'
            : 'FAQ terkait yang mungkin Anda butuhkan:';
        foreach (array_slice($matches, 1, 2) as $row) {
            $lines[] = '- ' . trim((string)$row['question']);
        }

        return trim(implode("\n", array_filter($lines, static fn(string $line): bool => $line !== '' && $line !== $question)));
    }

    /**
     * @param array<int, array<string, mixed>> $matches
     */
    private function composeWithLlm(string $message, array $matches, string $language): ?string
    {
        $faqContext = [];
        foreach ($matches as $index => $row) {
            $faqContext[] = ($index + 1) . '. '
                . 'Question: ' . (string)$row['question'] . "\n"
                . 'Answer: ' . (string)$row['answer'] . "\n"
                . 'Tags: ' . (string)($row['tags'] ?? '') . "\n"
                . 'Scope: ' . (string)$row['scope'];
        }

        $prompt = $language === 'en'
            ? "You are a coffee shop FAQ assistant. Answer only from the retrieved FAQ context. Be concise, direct, and do not invent facts.\n\nCustomer message:\n{$message}\n\nRetrieved FAQ context:\n" . implode("\n\n", $faqContext)
            : "Anda adalah asisten FAQ coffee shop. Jawab hanya dari konteks FAQ yang diambil. Singkat, langsung, dan jangan mengarang fakta.\n\nPesan customer:\n{$message}\n\nKonteks FAQ terambil:\n" . implode("\n\n", $faqContext);

        return $this->callLlm($prompt, 260);
    }

    private function loadConfig(): void
    {
        $rows = Database::getInstance()->query(
            'SELECT setting_key, setting_val FROM app_settings
             WHERE setting_key IN ("llm_provider","llm_api_key","llm_model")'
        )->fetchAll();
        $cfg = array_column($rows, 'setting_val', 'setting_key');
        $this->provider = (string)($cfg['llm_provider'] ?? 'none');
        $this->apiKey = (string)($cfg['llm_api_key'] ?? '');
        $this->model = (string)($cfg['llm_model'] ?? '');
    }

    private function callLlm(string $prompt, int $maxTokens): ?string
    {
        try {
            if ($this->provider === 'openai') {
                return $this->callOpenAi($prompt, $maxTokens);
            }
            if ($this->provider === 'anthropic') {
                return $this->callAnthropic($prompt, $maxTokens);
            }
            if ($this->provider === 'openrouter') {
                return $this->callOpenRouter($prompt, $maxTokens);
            }
        } catch (\Throwable $e) {
            error_log('[FaqRagResponder] ' . $e->getMessage());
        }

        return null;
    }

    private function callOpenAi(string $prompt, int $maxTokens): ?string
    {
        $payload = json_encode([
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);
        $response = $this->httpPost(
            'https://api.openai.com/v1/chat/completions',
            ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json'],
            (string)$payload
        );
        if ($response === null) {
            return null;
        }
        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function callAnthropic(string $prompt, int $maxTokens): ?string
    {
        $payload = json_encode([
            'model' => $this->model ?: 'claude-haiku-4-5-20251001',
            'max_tokens' => $maxTokens,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);
        $response = $this->httpPost(
            'https://api.anthropic.com/v1/messages',
            [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
            ],
            (string)$payload
        );
        if ($response === null) {
            return null;
        }
        $data = json_decode($response, true);
        return $data['content'][0]['text'] ?? null;
    }

    private function callOpenRouter(string $prompt, int $maxTokens): ?string
    {
        $payload = json_encode([
            'model' => $this->model ?: 'openai/gpt-4o-mini',
            'max_tokens' => $maxTokens,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);
        $response = $this->httpPost(
            'https://openrouter.ai/api/v1/chat/completions',
            [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: ' . (defined('BASE_URL') ? BASE_URL : 'http://localhost'),
                'X-Title: Toko Kopi FAQ RAG',
            ],
            (string)$payload
        );
        if ($response === null) {
            return null;
        }
        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function httpPost(string $url, array $headers, string $body): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_CONNECTTIMEOUT_MS => 5000,
            CURLOPT_TIMEOUT_MS => 12000,
        ]);
        $result = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $result === false) {
            throw new UnexpectedValueException('cURL error: ' . $err);
        }

        return $result ?: null;
    }
}
