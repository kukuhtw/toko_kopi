<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

function normalize_header(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim((string)$value, '_');
}

function truthy($value): int
{
    $value = strtolower(trim((string)$value));
    return in_array($value, ['1', 'true', 'yes', 'y', 'resep', 'wajib', 'required'], true) ? 1 : 0;
}

try {
    if (!isset($_FILES['csv'])) {
        throw new RuntimeException('CSV file required.');
    }

    $tmp = $_FILES['csv']['tmp_name'];

    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $handle = fopen($tmp, 'r');

    if (!$handle) {
        throw new RuntimeException('Unable to open CSV.');
    }

    $rawHeader = fgetcsv($handle);

    if (!$rawHeader) {
        throw new RuntimeException('CSV header is required.');
    }

    $header = array_map('normalize_header', $rawHeader);
    $required = ['product_name', 'bpom_no'];
    $missing = array_diff($required, $header);

    if (!empty($missing)) {
        throw new RuntimeException('Missing required column(s): ' . implode(', ', $missing));
    }

    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    $findProduct = $pdo->prepare(
        'SELECT mi.id
         FROM menu_items mi
         LEFT JOIN pharmacy_product_metadata pm ON pm.menu_item_id = mi.id
         WHERE pm.bpom_no = :bpom_no OR LOWER(mi.name) = LOWER(:product_name)
         LIMIT 1'
    );

    $insertProduct = $pdo->prepare(
        'INSERT INTO menu_items (name, description, price, is_available, created_at, updated_at)
         VALUES (:name, :description, :price, 1, NOW(), NOW())'
    );

    $updateProduct = $pdo->prepare(
        'UPDATE menu_items
         SET name = :name, description = :description, price = :price, updated_at = NOW()
         WHERE id = :id'
    );

    $findMetadata = $pdo->prepare(
        'SELECT id FROM pharmacy_product_metadata WHERE menu_item_id = :menu_item_id LIMIT 1'
    );

    $insertMetadata = $pdo->prepare(
        'INSERT INTO pharmacy_product_metadata
         (menu_item_id, generic_name, bpom_no, dosage, dosage_form, drug_class, manufacturer, requires_prescription, pharmacist_review_required, warning_text, created_at, updated_at)
         VALUES (:menu_item_id, :generic_name, :bpom_no, :dosage, :dosage_form, :drug_class, :manufacturer, :requires_prescription, :pharmacist_review_required, :warning_text, NOW(), NOW())'
    );

    $updateMetadata = $pdo->prepare(
        'UPDATE pharmacy_product_metadata
         SET generic_name = :generic_name,
             bpom_no = :bpom_no,
             dosage = :dosage,
             dosage_form = :dosage_form,
             drug_class = :drug_class,
             manufacturer = :manufacturer,
             requires_prescription = :requires_prescription,
             pharmacist_review_required = :pharmacist_review_required,
             warning_text = :warning_text,
             updated_at = NOW()
         WHERE menu_item_id = :menu_item_id'
    );

    $insertLog = $pdo->prepare(
        'INSERT INTO pharmacy_bpom_import_logs
         (file_name, rows_detected, rows_inserted, rows_updated, rows_skipped, status, message, created_at)
         VALUES (:file_name, :rows_detected, :rows_inserted, :rows_updated, :rows_skipped, :status, :message, NOW())'
    );

    $detected = 0;
    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];
    $seen = [];

    while (($row = fgetcsv($handle)) !== false) {
        $detected++;
        $data = array_combine($header, array_pad($row, count($header), null));

        if (!$data) {
            $skipped++;
            $errors[] = "Row {$detected}: invalid CSV row.";
            continue;
        }

        $productName = trim((string)($data['product_name'] ?? ''));
        $bpomNo = trim((string)($data['bpom_no'] ?? ''));

        if ($productName === '' || $bpomNo === '') {
            $skipped++;
            $errors[] = "Row {$detected}: product_name and bpom_no are required.";
            continue;
        }

        $dedupeKey = strtolower($bpomNo);

        if (isset($seen[$dedupeKey])) {
            $skipped++;
            $errors[] = "Row {$detected}: duplicate BPOM number inside CSV: {$bpomNo}.";
            continue;
        }

        $seen[$dedupeKey] = true;

        $description = trim((string)($data['description'] ?? $data['generic_name'] ?? ''));
        $price = (float)($data['default_price'] ?? $data['price'] ?? 0);
        $requiresPrescription = truthy($data['requires_prescription'] ?? 0);
        $pharmacistReviewRequired = $requiresPrescription || truthy($data['pharmacist_review_required'] ?? 0);

        $findProduct->execute([
            ':bpom_no' => $bpomNo,
            ':product_name' => $productName,
        ]);

        $existingProductId = $findProduct->fetchColumn();

        if ($existingProductId) {
            $menuItemId = (int)$existingProductId;
            $updateProduct->execute([
                ':id' => $menuItemId,
                ':name' => $productName,
                ':description' => $description,
                ':price' => $price,
            ]);
            $updated++;
        } else {
            $insertProduct->execute([
                ':name' => $productName,
                ':description' => $description,
                ':price' => $price,
            ]);
            $menuItemId = (int)$pdo->lastInsertId();
            $inserted++;
        }

        $metadataPayload = [
            ':menu_item_id' => $menuItemId,
            ':generic_name' => trim((string)($data['generic_name'] ?? '')),
            ':bpom_no' => $bpomNo,
            ':dosage' => trim((string)($data['dosage'] ?? '')),
            ':dosage_form' => trim((string)($data['dosage_form'] ?? '')),
            ':drug_class' => trim((string)($data['drug_class'] ?? '')),
            ':manufacturer' => trim((string)($data['manufacturer'] ?? '')),
            ':requires_prescription' => $requiresPrescription,
            ':pharmacist_review_required' => $pharmacistReviewRequired ? 1 : 0,
            ':warning_text' => trim((string)($data['warning_text'] ?? '')),
        ];

        $findMetadata->execute([':menu_item_id' => $menuItemId]);

        if ($findMetadata->fetchColumn()) {
            $updateMetadata->execute($metadataPayload);
        } else {
            $insertMetadata->execute($metadataPayload);
        }
    }

    fclose($handle);

    $insertLog->execute([
        ':file_name' => $_FILES['csv']['name'] ?? 'uploaded.csv',
        ':rows_detected' => $detected,
        ':rows_inserted' => $inserted,
        ':rows_updated' => $updated,
        ':rows_skipped' => $skipped,
        ':status' => empty($errors) ? 'success' : 'partial',
        ':message' => json_encode(array_slice($errors, 0, 50)),
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'rows_detected' => $detected,
        'rows_inserted' => $inserted,
        'rows_updated' => $updated,
        'rows_skipped' => $skipped,
        'errors' => $errors,
    ]);
} catch (Throwable $e) {
    if (isset($handle) && is_resource($handle)) {
        fclose($handle);
    }

    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
