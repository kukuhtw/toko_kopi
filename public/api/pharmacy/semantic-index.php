<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

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

    ksort($vector);
    return $vector;
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

        $index[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'] ?? '',
            'description' => $row['description'] ?? '',
            'requires_prescription' => (int)($row['requires_prescription'] ?? 0),
            'text' => $text,
            'vector' => build_vector($text),
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
            'engine' => 'local-token-vector',
            'generated_at' => date('c'),
            'rows' => $index,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    if ($result === false) {
        throw new RuntimeException('Failed to write semantic index file.');
    }

    echo json_encode([
        'success' => true,
        'engine' => 'local-token-vector',
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
