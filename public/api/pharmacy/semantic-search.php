<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

header('Content-Type: application/json');

function normalize_text(string $text): string
{
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]+/u', ' ', $text);
    return trim((string)preg_replace('/\s+/', ' ', (string)$text));
}

function build_vector(string $text): array
{
    $tokens = array_filter(explode(' ', normalize_text($text)));
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

    $queryVector = build_vector($query);
    $results = [];

    foreach ($index['rows'] as $row) {
        $score = cosine_similarity(
            $queryVector,
            $row['vector'] ?? []
        );

        if ($score <= 0) {
            continue;
        }

        $results[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'requires_prescription' => $row['requires_prescription'] ?? 0,
            'score' => round($score, 5),
        ];
    }

    usort($results, static function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $results = array_slice($results, 0, 20);

    echo json_encode([
        'success' => true,
        'engine' => 'local-token-vector',
        'query' => $query,
        'results' => $results,
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
