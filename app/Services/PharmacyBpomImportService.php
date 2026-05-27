<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use PDO;
use RuntimeException;

class PharmacyBpomImportService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getInstance();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function importCsv(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException('CSV file not found.');
        }

        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new RuntimeException('Unable to open CSV file.');
        }

        $header = fgetcsv($handle);

        $required = [
            'category',
            'product_name',
            'generic_name',
            'dosage',
            'manufacturer',
            'requires_prescription',
            'default_price'
        ];

        foreach ($required as $column) {
            if (!in_array($column, $header, true)) {
                throw new RuntimeException('Missing CSV column: ' . $column);
            }
        }

        $success = 0;
        $failed = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $data = array_combine($header, $row);

                $categoryId = $this->findOrCreateCategory($data['category']);

                $itemId = $this->insertMenuItem($categoryId, $data);

                $this->insertMetadata($itemId, $data);

                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = $e->getMessage();
            }
        }

        fclose($handle);

        return [
            'success_rows' => $success,
            'failed_rows' => $failed,
            'errors' => $errors,
        ];
    }

    private function findOrCreateCategory(string $name): int
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));

        $stmt = $this->pdo->prepare('SELECT id FROM menu_categories WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);

        $id = $stmt->fetchColumn();

        if ($id) {
            return (int)$id;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO menu_categories (name, slug, description, sort_order, is_active)
             VALUES (:name, :slug, :description, 0, 1)'
        );

        $insert->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':description' => 'Pharmacy imported category',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function insertMenuItem(int $categoryId, array $data): int
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $data['product_name']), '-'));

        $stmt = $this->pdo->prepare(
            'INSERT INTO menu_items
             (category_id, name, slug, description, price, min_toppings, max_toppings, is_available, is_active, sort_order)
             VALUES (:category_id, :name, :slug, :description, :price, 0, 0, 1, 1, 0)'
        );

        $stmt->execute([
            ':category_id' => $categoryId,
            ':name' => $data['product_name'],
            ':slug' => $slug,
            ':description' => $data['generic_name'] . ' ' . $data['dosage'],
            ':price' => (float)$data['default_price'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function insertMetadata(int $itemId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pharmacy_product_metadata
             (menu_item_id, generic_name, manufacturer, dosage, requires_prescription)
             VALUES (:menu_item_id, :generic_name, :manufacturer, :dosage, :requires_prescription)'
        );

        $stmt->execute([
            ':menu_item_id' => $itemId,
            ':generic_name' => $data['generic_name'],
            ':manufacturer' => $data['manufacturer'],
            ':dosage' => $data['dosage'],
            ':requires_prescription' => (int)$data['requires_prescription'],
        ]);
    }
}
