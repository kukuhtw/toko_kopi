<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

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

    return implode(' ', array_unique($expanded));
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

    return $vector;
}

function cosine_similarity(array $a, array $b): float
{
    $dot = 0.0;
    $normA = 0.0;
    $normB = 0.0;

    $keys = array_unique(array_merge(array_keys($a), array_keys($b)));

    foreach ($keys as $key) {
        $va = (float)($a[$key] ?? 0);
        $vb = (float)($b[$key] ?? 0);

        $dot += $va * $vb;
        $normA += $va * $va;
        $normB += $vb * $vb;
    }

    if ($normA <= 0 || $normB <= 0) {
        return 0.0;
    }

    return $dot / (sqrt($normA) * sqrt($normB));
}

function typo_score(string $query, string $text): float
{
    $queryTokens = array_filter(explode(' ', normalize_text($query)));
    $textTokens = array_filter(explode(' ', normalize_text($text)));

    if (empty($queryTokens) || empty($textTokens)) {
        return 0.0;
    }

    $scores = [];

    foreach ($queryTokens as $q) {
        $best = 0.0;

        foreach ($textTokens as $t) {
            $distance = levenshtein($q, $t);
            $maxLen = max(strlen($q), strlen($t));

            if ($maxLen <= 0) {
                continue;
            }

            $score = 1 - ($distance / $maxLen);
            $best = max($best, $score);
        }

        $scores[] = max(0.0, $best);
    }

    return array_sum($scores) / count($scores);
}

function openai_embedding(string $text): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $apiKey = getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? '');

    if ($apiKey === '') {
        return null;
    }

    $payload = json_encode([
        'model' => getenv('OPENAI_EMBEDDING_MODEL') ?: 'text-embedding-3-small',
        'input' => mb_substr($text, 0, 8000),
    ]);

    $ch = curl_init('https://api.openai.com/v1/embeddings');

    if ($ch === false) {
        return null;
    }

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
    $embedding = $json['data'][0]['embedding'] ?? null;

    return is_array($embedding) ? $embedding : null;
}

function embedding_cosine_similarity(array $a, array $b): float
{
    $dot = 0.0;
    $normA = 0.0;
    $normB = 0.0;

    $count = min(count($a), count($b));

    for ($i = 0; $i < $count; $i++) {
        $va = (float)$a[$i];
        $vb = (float)$b[$i];

        $dot += $va * $vb;
        $normA += $va * $va;
        $normB += $vb * $vb;
    }

    if ($normA <= 0 || $normB <= 0) {
        return 0.0;
    }

    return $dot / (sqrt($normA) * sqrt($normB));
}

try {
    $query = trim((string)($_GET['q'] ?? ''));

    if ($query === '') {
        throw new RuntimeException('Query required.');
    }

    $indexPath = dirname(__DIR__, 3) . '/storage/pharmacy/semantic-index.json';

    if (!file_exists($indexPath)) {
        throw new RuntimeException('Semantic index not found. Run semantic-index.php first.');
    }

    $index = json_decode((string)file_get_contents($indexPath), true);

    if (!$index || empty($index['rows'])) {
        throw new RuntimeException('Invalid semantic index data.');
    }

    $expandedQuery = expand_synonyms($query);
    $queryVector = build_vector($expandedQuery);
    $queryEmbedding = openai_embedding($expandedQuery);

    $results = [];

    foreach ($index['rows'] as $row) {
        $localScore = cosine_similarity(
            $queryVector,
            $row['vector'] ?? []
        );

        $typoBoost = typo_score($expandedQuery, $row['text'] ?? '');

        $embeddingScore = 0.0;

        if (is_array($queryEmbedding) && is_array($row['openai_embedding'] ?? null)) {
            $embeddingScore = embedding_cosine_similarity(
                $queryEmbedding,
                $row['openai_embedding']
            );
        }

        $finalScore = ($localScore * 0.50) + ($typoBoost * 0.15) + ($embeddingScore * 0.35);

        if ($finalScore <= 0.05) {
            continue;
        }

        $results[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'requires_prescription' => $row['requires_prescription'] ?? 0,
            'local_score' => round($localScore, 5),
            'typo_score' => round($typoBoost, 5),
            'embedding_score' => round($embeddingScore, 5),
            'final_score' => round($finalScore, 5),
        ];
    }

    usort($results, static function ($a, $b) {
        return $b['final_score'] <=> $a['final_score'];
    });

    $results = array_slice($results, 0, 20);

    echo json_encode([
        'success' => true,
        'engine' => is_array($queryEmbedding)
            ? 'openai-embedding-hybrid-search'
            : 'local-vector-hybrid-search',
        'query' => $query,
        'expanded_query' => $expandedQuery,
        'results' => $results,
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
