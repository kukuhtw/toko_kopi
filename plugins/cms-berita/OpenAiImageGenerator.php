<?php

declare(strict_types=1);

use App\Config\Database;

class OpenAiImageGenerator
{
    private const API_URL = 'https://api.openai.com/v1/images/generations';
    private const DIRECTORY = 'berita';

    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function generate(array $input): array
    {
        $config = $this->getConfig();
        if ($config['api_key'] === '') {
            throw new \RuntimeException('OPENAI_API_KEY belum diatur. Isi di .env atau set provider global ke OpenAI dengan API key yang valid.');
        }

        $topic = trim((string)($input['topic'] ?? ''));
        $prompt = trim((string)($input['prompt'] ?? ''));

        if ($prompt === '') {
            if ($topic === '') {
                throw new \InvalidArgumentException('Prompt gambar atau topik artikel wajib diisi.');
            }
            $prompt = 'Buat cover artikel berita toko kopi yang editorial, bersih, menarik, dan realistis untuk topik: ' . $topic . '.';
        }

        $branchLabel = 'untuk semua cabang';
        if (!empty($input['branch_id'])) {
            $branchName = $this->getBranchName((int)$input['branch_id']);
            if ($branchName !== null) {
                $branchLabel = 'untuk cabang ' . $branchName;
            }
        }

        $style = trim((string)($input['style'] ?? ''));
        $style = $style !== '' ? $style : 'foto produk / lifestyle coffee shop yang realistis';

        $fullPrompt = implode("\n", [
            'Buat 1 gambar cover horizontal-leaning yang cocok untuk artikel berita toko kopi.',
            'Tema utama: ' . ($topic !== '' ? $topic : 'berita toko kopi'),
            'Gaya visual: ' . $style,
            'Konteks brand: ' . $branchLabel,
            'Instruksi tambahan: ' . $prompt,
            'Hindari teks watermark, tulisan panjang, logo merek lain, dan elemen UI.',
        ]);

        $size = (string)($input['size'] ?? '1536x1024');
        if (!in_array($size, ['1024x1024', '1536x1024', '1024x1536'], true)) {
            $size = '1536x1024';
        }

        $quality = (string)($input['quality'] ?? 'medium');
        if (!in_array($quality, ['low', 'medium', 'high'], true)) {
            $quality = 'medium';
        }

        $payload = [
            'model' => $config['model'],
            'prompt' => $fullPrompt,
            'size' => $size,
            'quality' => $quality,
            'output_format' => 'png',
        ];

        $data = $this->post($config['api_key'], $payload);
        $imageBase64 = (string)($data['data'][0]['b64_json'] ?? '');
        if ($imageBase64 === '') {
            throw new \UnexpectedValueException('OpenAI tidak mengembalikan data gambar.');
        }

        $relativePath = $this->saveImage($imageBase64, $topic !== '' ? $topic : 'cover-berita');
        return [
            'relative_path' => $relativePath,
            'public_url' => $this->publicUrl($relativePath),
        ];
    }

    private function getConfig(): array
    {
        $envKey = trim((string)($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: ''));
        $envModel = trim((string)($_ENV['OPENAI_IMAGE_MODEL'] ?? getenv('OPENAI_IMAGE_MODEL') ?: ''));

        if ($envKey !== '') {
            return [
                'api_key' => $envKey,
                'model' => $envModel !== '' ? $envModel : 'gpt-image-1-mini',
            ];
        }

        $rows = $this->db->query(
            'SELECT setting_key, setting_val FROM app_settings WHERE setting_key IN ("llm_provider","llm_api_key")'
        )->fetchAll();
        $cfg = array_column($rows, 'setting_val', 'setting_key');

        if (($cfg['llm_provider'] ?? '') === 'openai' && !empty($cfg['llm_api_key'])) {
            return [
                'api_key' => (string)$cfg['llm_api_key'],
                'model' => 'gpt-image-1-mini',
            ];
        }

        return [
            'api_key' => '',
            'model' => 'gpt-image-1-mini',
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
            CURLOPT_TIMEOUT_MS => 45000,
        ]);

        $result = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $result === false) {
            throw new \UnexpectedValueException('OpenAI image cURL error: ' . $err);
        }

        $data = json_decode($result ?: '{}', true);
        if (!is_array($data)) {
            throw new \UnexpectedValueException('OpenAI image response is not valid JSON.');
        }

        if ($code >= 400 || isset($data['error'])) {
            $message = (string)($data['error']['message'] ?? ('OpenAI image API error HTTP ' . $code));
            throw new \UnexpectedValueException($message);
        }

        return $data;
    }

    private function saveImage(string $imageBase64, string $seed): string
    {
        $binary = base64_decode($imageBase64, true);
        if ($binary === false) {
            throw new \UnexpectedValueException('Data gambar dari OpenAI tidak valid.');
        }

        $targetDir = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . self::DIRECTORY;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Folder upload berita tidak bisa dibuat.');
        }

        $safeSeed = $this->slugify($seed);
        if ($safeSeed === '') {
            $safeSeed = 'cover-berita';
        }

        $fileName = sprintf(
            '%s-%s-%s.png',
            $safeSeed,
            date('YmdHis'),
            substr(bin2hex(random_bytes(4)), 0, 8)
        );

        $absolutePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
        if (file_put_contents($absolutePath, $binary) === false) {
            throw new \RuntimeException('Gagal menyimpan gambar hasil generate.');
        }

        return self::DIRECTORY . '/' . $fileName;
    }

    private function publicUrl(string $relativePath): string
    {
        $base = str_ends_with(BASE_URL, '/public')
            ? substr(BASE_URL, 0, -7)
            : BASE_URL;

        return rtrim($base, '/') . '/uploads/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9\s-]/', '', $value) ?? '';
        $value = preg_replace('/[\s-]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
