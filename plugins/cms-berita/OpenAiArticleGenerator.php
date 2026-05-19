<?php

declare(strict_types=1);

use App\Config\Database;

class OpenAiArticleGenerator
{
    private const API_URL = 'https://api.openai.com/v1/responses';

    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function generate(array $brief): array
    {
        $config = $this->getConfig();
        if ($config['api_key'] === '') {
            throw new \RuntimeException('OPENAI_API_KEY belum diatur. Isi di .env atau set provider global ke OpenAI dengan API key yang valid.');
        }

        $topic = trim((string)($brief['topic'] ?? ''));
        if ($topic === '') {
            throw new \InvalidArgumentException('Topik artikel wajib diisi untuk generate dengan OpenAI.');
        }

        $scopeLine = 'Global / semua cabang';
        if (!empty($brief['branch_id'])) {
            $branchName = $this->getBranchName((int)$brief['branch_id']);
            if ($branchName !== null) {
                $scopeLine = 'Cabang: ' . $branchName;
            }
        }

        $length = (string)($brief['desired_length'] ?? 'medium');
        $lengthGuide = match ($length) {
            'short' => 'sekitar 250-400 kata',
            'long' => 'sekitar 700-1000 kata',
            default => 'sekitar 450-700 kata',
        };

        $prompt = implode("\n", [
            'Buat artikel berita toko kopi dalam bahasa Indonesia yang natural, meyakinkan, dan siap dipublikasikan.',
            'Konteks target artikel:',
            '- Topik utama: ' . $topic,
            '- Sudut bahasan: ' . $this->valueOrFallback($brief['angle'] ?? '', 'umum, informatif, dan relevan untuk pelanggan'),
            '- Audiens: ' . $this->valueOrFallback($brief['audience'] ?? '', 'pelanggan toko kopi, pengunjung baru, dan followers media sosial'),
            '- Tone: ' . $this->valueOrFallback($brief['tone'] ?? '', 'hangat, profesional, dan engaging'),
            '- Kata kunci penting: ' . $this->valueOrFallback($brief['keywords'] ?? '', 'kopi, promo, menu, toko kopi'),
            '- CTA: ' . $this->valueOrFallback($brief['cta'] ?? '', 'ajak pembaca datang ke toko atau mencoba menu yang dibahas'),
            '- Cakupan berita: ' . $scopeLine,
            '- Panjang artikel: ' . $lengthGuide,
            '- Catatan tambahan: ' . $this->valueOrFallback($brief['notes'] ?? '', 'tidak ada'),
            '',
            'Kembalikan output JSON valid sesuai schema. Isi "content" harus berupa artikel lengkap dengan paragraf-paragraf yang rapi, tanpa markdown, tanpa bullet, dan tanpa pembuka seperti "Berikut artikelnya".',
        ]);

        $payload = [
            'model' => $config['model'],
            'input' => [
                [
                    'role' => 'developer',
                    'content' => 'Anda adalah editor konten senior untuk brand toko kopi di Indonesia. Tulis artikel yang padat, jelas, dan terasa seperti copywriter manusia.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.9,
            'max_output_tokens' => 1800,
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'cms_news_article',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'excerpt' => ['type' => 'string'],
                            'content' => ['type' => 'string'],
                        ],
                        'required' => ['title', 'excerpt', 'content'],
                    ],
                ],
            ],
        ];

        $data = $this->post($config['api_key'], $payload);
        $jsonText = trim((string)($data['output_text'] ?? $this->extractOutputText($data)));
        $decoded = json_decode($jsonText, true);

        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('OpenAI mengembalikan format yang tidak bisa dibaca sebagai JSON artikel.');
        }

        $title = trim((string)($decoded['title'] ?? ''));
        $excerpt = trim((string)($decoded['excerpt'] ?? ''));
        $content = trim((string)($decoded['content'] ?? ''));

        if ($title === '' || $content === '') {
            throw new \UnexpectedValueException('Respons OpenAI tidak berisi judul atau isi artikel yang valid.');
        }

        return [
            'title' => $title,
            'excerpt' => $excerpt,
            'content' => $content,
        ];
    }

    private function getConfig(): array
    {
        $envKey = trim((string)($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: ''));
        $envModel = trim((string)($_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL') ?: ''));

        if ($envKey !== '') {
            return [
                'api_key' => $envKey,
                'model' => $envModel !== '' ? $envModel : 'gpt-4.1-mini',
            ];
        }

        $rows = $this->db->query(
            'SELECT setting_key, setting_val FROM app_settings WHERE setting_key IN ("llm_provider","llm_api_key","llm_model")'
        )->fetchAll();
        $cfg = array_column($rows, 'setting_val', 'setting_key');

        if (($cfg['llm_provider'] ?? '') === 'openai' && !empty($cfg['llm_api_key'])) {
            return [
                'api_key' => (string)$cfg['llm_api_key'],
                'model' => !empty($cfg['llm_model']) ? (string)$cfg['llm_model'] : 'gpt-4.1-mini',
            ];
        }

        return [
            'api_key' => '',
            'model' => 'gpt-4.1-mini',
        ];
    }

    private function getBranchName(int $branchId): ?string
    {
        if ($branchId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT name FROM branches WHERE id = ? LIMIT 1');
        $stmt->execute([$branchId]);
        $name = $stmt->fetchColumn();
        return $name !== false ? (string)$name : null;
    }

    private function post(string $apiKey, array $payload): array
    {
        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_CONNECTTIMEOUT_MS => 5000,
            CURLOPT_TIMEOUT_MS => 25000,
        ]);

        $result = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $result === false) {
            throw new \UnexpectedValueException('OpenAI cURL error: ' . $err);
        }

        $data = json_decode($result ?: '{}', true);
        if (!is_array($data)) {
            throw new \UnexpectedValueException('OpenAI response is not valid JSON.');
        }

        if ($code >= 400 || isset($data['error'])) {
            $message = (string)($data['error']['message'] ?? ('OpenAI API error HTTP ' . $code));
            throw new \UnexpectedValueException($message);
        }

        return $data;
    }

    private function extractOutputText(array $data): string
    {
        foreach (($data['output'] ?? []) as $item) {
            foreach (($item['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                    return (string)$content['text'];
                }
            }
        }

        return '';
    }

    private function valueOrFallback(mixed $value, string $fallback): string
    {
        $text = trim((string)$value);
        return $text !== '' ? $text : $fallback;
    }
}
