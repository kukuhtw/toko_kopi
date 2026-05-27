<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

function pharmacy_synonyms(): array
{
    return [
        'demam' => ['panas', 'meriang', 'suhu tinggi', 'fever'],
        'panas' => ['demam', 'meriang', 'fever'],
        'flu' => ['pilek', 'influenza', 'hidung meler', 'bersin'],
        'pilek' => ['flu', 'hidung meler', 'bersin'],
        'batuk' => ['cough', 'tenggorokan gatal'],
        'berdahak' => ['dahak', 'mukus', 'produktif'],
        'kering' => ['batuk kering', 'tidak berdahak'],
        'nyeri' => ['sakit', 'pain', 'linu'],
        'sakit' => ['nyeri', 'pain'],
        'kepala' => ['pusing', 'migraine', 'migrain'],
        'lambung' => ['maag', 'asam lambung', 'gastritis', 'perut perih'],
        'maag' => ['lambung', 'asam lambung', 'gastritis'],
        'mual' => ['nausea', 'ingin muntah'],
        'diare' => ['mencret', 'buang air besar cair'],
        'alergi' => ['gatal', 'biduran', 'ruam'],
        'gatal' => ['alergi', 'ruam', 'biduran'],
        'vitamin' => ['suplemen', 'daya tahan tubuh', 'imunitas'],
        'imun' => ['imunitas', 'daya tahan tubuh', 'vitamin'],
        'luka' => ['cedera', 'gores', 'lecet'],
        'antiseptik' => ['luka', 'disinfektan', 'pembersih luka'],
        'anak' => ['balita', 'bayi', 'pediatric', 'sirup'],
        'dewasa' => ['adult', 'tablet', 'kapsul'],
    ];
}

function normalize_text(string $text): string
{
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]+/u', ' ', $text);
    return trim((string)preg_replace('/\s+/', ' ', (string)$text));
}

function expand_synonyms(string $text): string
{
    $normalized = normalize_text($text);
    $tokens = array_filter(explode(' ', $normalized));
    $expanded = $tokens;
    $dict = pharmacy_synonyms();

    foreach ($tokens as $token) {
        if (isset($dict[$token])) {
            foreach ($dict[$token] as $synonym) {
                $expanded[] = $synonym;
            }
        }
    }

    return implode(' ', $expanded);
}

function build_vector(string $text): array
{
    $tokens = array_filter(explode(' ', normalize_text(expand_synonyms($text))));
    $vector = [];

    foreach ($tokens as $token) {
        if (strlen($token) < 3) {
            continue;
        }

        $vector[$token] = ($vector[$token] ?? 0) + 1;
    }

    ksort($vector);
    return $vector;
}

function openai_embedding(string $text): ?array
{
    $apiKey = getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? '');

    if ($apiKey === '') {
        return null;
    }

    $payload = json_encode([
        'model' => getenv('OPENAI_EMBEDDING_MODEL') ?: 'text-embedding-3-small',
        'input' => $text,
    ]);

    $ch = curl_init('https://api.openai.com/v1/embeddings');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        return null;
    }

    $json = json_decode((string)$response, true);
    return $json['data'][0]['embedding'] ?? null;
}

try {
    $pdo = Database::getInstance();

    $stmt = $pdo->query(
        "SELECT mi.id, mi.name, mi.description,
                pm.generic_name,
                pm.manufacturer,
                pm.dosage,
                pm.dosage_form,
                pm.drug_class,
                pm.bpom_no,
                pm.requires_prescription,
                pm.warning_text
         FROM menu_items mi
         LEFT JOIN pharmacy_product_metadata pm ON pm.menu_item_id = mi.id"
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $index = [];
    $openAiCount = 0;

    foreach ($rows as $row) {
        $text = implode(' ', array_filter([
            $row['name'] ?? '',
            $row['description'] ?? '',
            $row['generic_name'] ?? '',
            $row['manufacturer'] ?? '',
            $row['dosage'] ?? '',
            $row['dosage_form'] ?? '',
            $row['drug_class'] ?? '',
            $row['bpom_no'] ?? '',
            $row['warning_text'] ?? '',
        ]));

        $expandedText = trim($text . ' ' . expand_synonyms($text));
        $embedding = openai_embedding($expandedText);

        if (is_array($embedding)) {
            $openAiCount++;
        }

        $index[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'] ?? '',
            'description' => $row['description'] ?? '',
            'requires_prescription' => (int)($row['requires_prescription'] ?? 0),
            'text' => $text,
            'expanded_text' => $expandedText,
            'vector' => build_vector($expandedText),
            'openai_embedding' => $embedding,
        ];
    }

    $storageDir = dirname(__DIR__, 3) . '/storage/pharmacy';

    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }

    if (!is_dir($storageDir)) {
        throw new RuntimeException('Semantic index directory unavailable.');
    }

    $path = $storageDir . '/semantic-index.json';

    $result = file_put_contents(
        $path,
        json_encode([
            'engine' => $openAiCount > 0 ? 'openai-embedding-with-local-fallback' : 'local-token-vector',
            'openai_embedding_model' => getenv('OPENAI_EMBEDDING_MODEL') ?: 'text-embedding-3-small',
            'openai_embeddings_created' => $openAiCount,
            'generated_at' => date('c'),
            'rows' => $index,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    if ($result === false) {
        throw new RuntimeException('Failed to write semantic index file.');
    }

    echo json_encode([
        'success' => true,
        'engine' => $openAiCount > 0 ? 'openai-embedding-with-local-fallback' : 'local-token-vector',
        'openai_embeddings_created' => $openAiCount,
        'indexed_rows' => count($index),
        'index_path' => $path,
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
