<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    if (!isset($_FILES['csv'])) {
        throw new RuntimeException('CSV file required.');
    }

    $tmp = $_FILES['csv']['tmp_name'];

    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $pdo = Database::getInstance();

    $handle = fopen($tmp, 'r');

    if (!$handle) {
        throw new RuntimeException('Unable to open CSV.');
    }

    $header = fgetcsv($handle);

    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = array_combine($header, $row);
    }

    fclose($handle);

    echo json_encode([
        'success' => true,
        'rows_detected' => count($rows),
        'sample' => array_slice($rows, 0, 3),
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
