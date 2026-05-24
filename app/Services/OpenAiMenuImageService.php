<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Helpers\MenuImage;
use PDO;
use RuntimeException;
use UnexpectedValueException;

class OpenAiMenuImageService
{
    private const API_URL = 'https://api.openai.com/v1/images/generations';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function generateForMenu(array $menuItem, array $input = [], ?int $branchId = null): array
    {
        $config = $this->getConfig();
        if ($config['api_key'] === '') {
            throw new RuntimeException('OPENAI_API_KEY belum diatur. Isi di .env atau set provider global ke OpenAI dengan API key yang valid.');
        }

        $name = trim((string)($menuItem['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Nama menu belum tersedia untuk generate gambar.');
        }

        $description = trim((string)($menuItem['description'] ?? ''));
        $categoryName = trim((string)($menuItem['category_name'] ?? ''));
        $branchLabel = $this->getBranchLabel($branchId);

        $customPrompt = trim((string)($input['prompt'] ?? ''));
        $style = trim((string)($input['style'] ?? ''));
        if ($style === '') {
            $style = 'foto produk studio realistis, premium, pencahayaan hangat, clean background, cocok untuk katalog menu restoran';
        }

        $size = (string)($input['size'] ?? '1024x1024');
        if (!in_array($size, ['1024x1024', '1536x1024', '1024x1536'], true)) {
            $size = '1024x1024';
        }

        $quality = (string)($input['quality'] ?? 'medium');
        if (!in_array($quality, ['low', 'medium', 'high'], true)) {
            $quality = 'medium';
        }

        $promptLines = [
            'Buat 1 foto produk menu yang realistis dan menggugah selera untuk katalog digital coffee shop / restoran.',
            'Nama menu: ' . $name,
            'Kategori: ' . ($categoryName !== '' ? $categoryName : 'menu makanan/minuman'),
            'Deskripsi menu: ' . ($description !== '' ? $description : 'tidak ada deskripsi tambahan'),
            'Gaya visual: ' . $style,
            'Konteks outlet: ' . $branchLabel,
            'Fokus pada produk utama, framing rapi, lighting profesional, tanpa watermark, tanpa teks, tanpa logo brand lain, tanpa elemen UI.',
        ];

        if ($customPrompt !== '') {
            $promptLines[] = 'Instruksi tambahan: ' . $customPrompt;
        }

        $payload = [
            'model' => $config['model'],
            'prompt' => implode("\n", $promptLines),
            'size' => $size,
            'quality' => $quality,
            'output_format' => 'png',
        ];

        $data = $this->post($config['api_key'], $payload);
        $imageBase64 = (string)($data['data'][0]['b64_json'] ?? '');
        if ($imageBase64 === '') {
            throw new UnexpectedValueException('OpenAI tidak mengembalikan data gambar.');
        }

        $binary = base64_decode($imageBase64, true);
        if ($binary === false) {
            throw new UnexpectedValueException('Data gambar dari OpenAI tidak valid.');
        }

        $relativePath = MenuImage::storeGeneratedBinary($binary, $name, 'png');

        return [
            'relative_path' => $relativePath,
            'public_url' => MenuImage::publicUrl($relativePath),
            'prompt_used' => $payload['prompt'],
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
                'model' => $envModel !== '' ? $envModel : 'gpt-image-1-mini',
            ];
        }

        return [
            'api_key' => '',
            'model' => $envModel !== '' ? $envModel : 'gpt-image-1-mini',
        ];
    }

    private function getBranchLabel(?int $branchId): string
    {
        if (($branchId ?? 0) <= 0) {
            return 'untuk semua cabang';
        }

        $stmt = $this->db->prepare('SELECT name FROM branches WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$branchId]);
        $name = $stmt->fetchColumn();
        if ($name === false) {
            return 'untuk cabang #' . (int)$branchId;
        }

        return 'untuk cabang ' . (string)$name;
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
        $error = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error || $result === false) {
            throw new UnexpectedValueException('OpenAI image cURL error: ' . $error);
        }

        $data = json_decode($result ?: '{}', true);
        if (!is_array($data)) {
            throw new UnexpectedValueException('OpenAI image response is not valid JSON.');
        }

        if ($code >= 400 || isset($data['error'])) {
            $message = (string)($data['error']['message'] ?? ('OpenAI image API error HTTP ' . $code));
            throw new UnexpectedValueException($message);
        }

        return $data;
    }
}
