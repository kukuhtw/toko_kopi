<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Helpers\Sanitize;
use App\Models\BranchModel;
use PDO;

class MenuCatalogAiTransferService
{
    private const ENTITY_FIELDS = [
        'menu_items' => [
            'category_name', 'category_slug', 'item_name', 'item_slug', 'description',
            'global_price_idr', 'branch_price', 'min_toppings', 'max_toppings',
            'image_path', 'global_is_available', 'branch_is_available', 'is_active', 'sort_order', 'branch_note',
        ],
        'menu_variants' => [
            'category_name', 'item_name', 'item_slug', 'variant_label', 'variant_slug',
            'global_delta_idr', 'branch_delta', 'is_active', 'sort_order',
        ],
        'menu_toppings' => [
            'topping_name', 'topping_slug', 'price_delta', 'sort_order', 'is_active', 'linked_items',
        ],
        'branch_availability' => [
            'category_name', 'item_name', 'item_slug', 'branch_price', 'branch_is_available', 'note',
        ],
        'menu_item_toppings' => [
            'item_name', 'item_slug', 'topping_name', 'topping_slug', 'sort_order',
        ],
        'ignore' => [],
    ];

    private PDO $db;
    private SpreadsheetTableService $spreadsheet;
    private AdminStructuredLlmService $llm;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->spreadsheet = new SpreadsheetTableService();
        $this->llm = new AdminStructuredLlmService();
    }

    public function exportBranchWorkbook(int $branchId): array
    {
        $currency = (new BranchModel())->getCurrency($branchId) ?: 'IDR';
        $itemColumns = [
            'category_name', 'category_slug', 'item_name', 'item_slug', 'description',
            'global_price_idr', 'min_toppings', 'max_toppings',
            'image_path', 'global_is_available', 'is_active', 'sort_order',
        ];
        $variantColumns = [
            'category_name', 'item_name', 'item_slug', 'variant_label', 'variant_slug',
            'global_delta_idr', 'branch_delta', 'is_active', 'sort_order',
        ];
        $toppingColumns = [
            'topping_name', 'topping_slug', 'price_delta', 'sort_order', 'is_active', 'linked_items',
        ];
        $availabilityColumns = [
            'category_name', 'item_name', 'item_slug', 'branch_price', 'branch_is_available', 'note',
        ];
        $items = $this->db->prepare(
            "SELECT mc.name AS category_name, mc.slug AS category_slug,
                    mi.name AS item_name, mi.slug AS item_slug, mi.description,
                    mi.price AS global_price_idr, mi.min_toppings, mi.max_toppings,
                    mi.image_path, mi.is_available AS global_is_available, mi.is_active, mi.sort_order,
                    bmo.custom_price AS branch_price, COALESCE(bmo.is_available, mi.is_available) AS branch_is_available,
                    bmo.note AS branch_note
             FROM menu_items mi
             JOIN menu_categories mc ON mc.id = mi.category_id
             LEFT JOIN branch_menu_overrides bmo
                    ON bmo.menu_item_id = mi.id AND bmo.branch_id = ?
             ORDER BY mc.sort_order, mi.sort_order, mi.name"
        );
        $items->execute([$branchId]);

        $variants = $this->db->prepare(
            "SELECT mc.name AS category_name, mi.name AS item_name, mi.slug AS item_slug,
                    v.label AS variant_label, v.slug AS variant_slug, v.price_delta AS global_delta_idr,
                    v.sort_order, v.is_active, bvo.price_delta AS branch_delta
             FROM menu_item_variants v
             JOIN menu_items mi ON mi.id = v.menu_item_id
             JOIN menu_categories mc ON mc.id = mi.category_id
             LEFT JOIN branch_menu_variant_overrides bvo
                    ON bvo.variant_id = v.id AND bvo.branch_id = ? AND bvo.is_active = 1
             ORDER BY mc.sort_order, mi.name, v.sort_order, v.label"
        );
        $variants->execute([$branchId]);

        $toppings = $this->db->query(
            "SELECT t.name AS topping_name, t.slug AS topping_slug, t.price_delta,
                    t.sort_order, t.is_active,
                    GROUP_CONCAT(mi.name ORDER BY mi.name SEPARATOR ' | ') AS linked_items
             FROM menu_toppings t
             LEFT JOIN menu_item_toppings mit ON mit.topping_id = t.id
             LEFT JOIN menu_items mi ON mi.id = mit.menu_item_id
             GROUP BY t.id
             ORDER BY t.sort_order, t.name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $availability = $this->db->prepare(
            "SELECT mc.name AS category_name, mi.name AS item_name, mi.slug AS item_slug,
                    bmo.custom_price AS branch_price, COALESCE(bmo.is_available, mi.is_available) AS branch_is_available,
                    bmo.note
             FROM menu_items mi
             JOIN menu_categories mc ON mc.id = mi.category_id
             LEFT JOIN branch_menu_overrides bmo
                    ON bmo.menu_item_id = mi.id AND bmo.branch_id = ?
             ORDER BY mc.sort_order, mi.name"
        );
        $availability->execute([$branchId]);

        $notes = $this->buildAiExportNotes($branchId, $currency);
        $sheets = [
            [
                'name' => 'Items',
                'rows' => array_merge(
                    [$itemColumns],
                    [$this->buildSheetNoteRow($itemColumns, '__NOTE__ Edit data master produk di sheet ini: kategori, nama, deskripsi, harga global, topping min/max, status global.')],
                    $this->assocRowsToSheetRows($items->fetchAll(PDO::FETCH_ASSOC), $itemColumns)
                ),
            ],
            [
                'name' => 'Variants',
                'rows' => array_merge(
                    [$variantColumns],
                    [$this->buildSheetNoteRow($variantColumns, '__NOTE__ Edit variant/size di sheet ini. branch_delta dipakai untuk override harga variant per cabang.')],
                    $this->assocRowsToSheetRows($variants->fetchAll(PDO::FETCH_ASSOC), $variantColumns)
                ),
            ],
            [
                'name' => 'Toppings',
                'rows' => array_merge(
                    [$toppingColumns],
                    [$this->buildSheetNoteRow($toppingColumns, '__NOTE__ Edit data topping dan relasi topping ke item di sheet ini.')],
                    $this->assocRowsToSheetRows($toppings, $toppingColumns)
                ),
            ],
            [
                'name' => 'Availability',
                'rows' => array_merge(
                    [$availabilityColumns],
                    [$this->buildSheetNoteRow($availabilityColumns, '__NOTE__ Edit override cabang hanya di sheet ini: branch_price, branch_is_available, dan note.')],
                    $this->assocRowsToSheetRows($availability->fetchAll(PDO::FETCH_ASSOC), $availabilityColumns)
                ),
            ],
            [
                'name' => 'AI Notes',
                'rows' => $notes,
            ],
        ];

        return [
            'filename' => 'menu-catalog-ai-' . $branchId . '-' . date('Ymd-His') . '.xls',
            'content' => $this->spreadsheet->renderXmlWorkbook($sheets),
            'llm_provider' => $this->llm->getProvider(),
            'llm_model' => $this->llm->getModel(),
        ];
    }

    public function importBranchWorkbook(int $branchId, string $path, string $originalName, ?array $manualMappings = null): array
    {
        $tables = $this->spreadsheet->readTables($path, $originalName);
        if ($tables === []) {
            throw new \RuntimeException('File tidak berisi sheet yang bisa dibaca.');
        }

        $mappedSheets = $this->resolveMappings($tables, $manualMappings);
        $summary = [
            'inserted_items' => 0,
            'updated_items' => 0,
            'inserted_variants' => 0,
            'updated_variants' => 0,
            'inserted_toppings' => 0,
            'updated_toppings' => 0,
            'linked_toppings' => 0,
            'updated_availability' => 0,
            'skipped_rows' => 0,
            'warnings' => [],
            'sheet_mappings' => $mappedSheets,
            'llm_provider' => $this->llm->getProvider(),
            'llm_model' => $this->llm->getModel(),
        ];
        $itemsSheetOverridesTouched = [];

        $this->db->beginTransaction();
        try {
            foreach ($tables as $index => $table) {
                $mapping = $mappedSheets[$index] ?? null;
                if (!$mapping || ($mapping['entity'] ?? 'ignore') === 'ignore') {
                    continue;
                }

                $rows = $this->normalizeTableRows($table['rows'] ?? []);
                if ($rows === []) {
                    continue;
                }

                $headers = array_shift($rows);
                $fieldMap = $this->normalizeFieldMap((array)($mapping['field_map'] ?? []), $headers);

                foreach ($rows as $row) {
                    $assoc = $this->rowToAssoc($headers, $row);
                    if ($this->rowIsEmpty($assoc)) {
                        continue;
                    }

                    $canonical = $this->toCanonicalRow($assoc, $fieldMap);
                    $entity = (string)$mapping['entity'];
                    $handled = match ($entity) {
                        'menu_items' => $this->importMenuItemRow($branchId, $canonical, $summary, $itemsSheetOverridesTouched),
                        'menu_variants' => $this->importVariantRow($branchId, $canonical, $summary),
                        'menu_toppings' => $this->importToppingRow($canonical, $summary),
                        'branch_availability' => $this->importAvailabilityRow($branchId, $canonical, $summary, $itemsSheetOverridesTouched),
                        'menu_item_toppings' => $this->importItemToppingLinkRow($canonical, $summary),
                        default => false,
                    };

                    if (!$handled) {
                        $summary['skipped_rows']++;
                    }
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $summary;
    }

    public function previewBranchWorkbook(int $branchId, string $path, string $originalName): array
    {
        $tables = $this->spreadsheet->readTables($path, $originalName);
        if ($tables === []) {
            throw new \RuntimeException('File tidak berisi sheet yang bisa dibaca.');
        }

        $mappedSheets = $this->detectSheetMappings($tables);
        $previewSheets = [];
        $totalRows = 0;

        foreach ($tables as $index => $table) {
            $rows = $this->normalizeTableRows($table['rows'] ?? []);
            $headers = $rows[0] ?? [];
            $dataRows = array_slice($rows, 1);
            $totalRows += count($dataRows);
            $previewSheets[] = [
                'sheet_name' => (string)($table['name'] ?? ('Sheet ' . ($index + 1))),
                'entity' => (string)($mappedSheets[$index]['entity'] ?? 'ignore'),
                'confidence' => (float)($mappedSheets[$index]['confidence'] ?? 0),
                'notes' => (string)($mappedSheets[$index]['notes'] ?? ''),
                'headers' => $headers,
                'field_map' => (array)($mappedSheets[$index]['field_map'] ?? []),
                'row_count' => count($dataRows),
                'sample_rows' => array_slice($dataRows, 0, 3),
            ];
        }

        return [
            'branch_id' => $branchId,
            'file_name' => $originalName,
            'sheet_count' => count($tables),
            'total_rows' => $totalRows,
            'sheets' => $previewSheets,
            'entity_field_options' => self::ENTITY_FIELDS,
            'llm_provider' => $this->llm->getProvider(),
            'llm_model' => $this->llm->getModel(),
        ];
    }

    public function getEntityFieldOptions(): array
    {
        return self::ENTITY_FIELDS;
    }

    private function buildAiExportNotes(int $branchId, string $currency): array
    {
        $fallbackText = [
            ['section', 'note'],
            ['overview', 'Workbook ini berisi 4 sheet utama: Items, Variants, Toppings, Availability.'],
            ['import_rule', 'Saat import ulang, sistem akan mencoba mengenali header dengan AI lalu melakukan update/insert sesuai schema menu.'],
            ['branch_context', 'Branch ID: ' . $branchId . ' | Mata uang cabang: ' . $currency],
            ['tips', 'Pertahankan item_slug dan variant_slug jika ingin update record yang sudah ada dengan lebih akurat.'],
            ['availability', 'branch_price, branch_is_available, dan note sekarang hanya ada di sheet Availability agar tidak bentrok dengan sheet Items.'],
        ];

        $text = $this->llm->completeText(
            'You are a data operations assistant for a coffee shop catalog workbook.',
            'Write 4 short import notes in Indonesian for an Excel workbook with sheets: Items, Variants, Toppings, Availability. Mention that the system uses AI to map columns, then performs deterministic upsert to SQL tables. Keep each note short and actionable. Return plain text with one note per line.',
            220
        );

        if ($text === null || trim($text) === '') {
            return $fallbackText;
        }

        $rows = [['section', 'note']];
        foreach (preg_split("/\r\n|\n|\r/", trim($text)) ?: [] as $idx => $line) {
            $line = trim(preg_replace('/^\d+[\).\s-]*/', '', $line) ?? $line);
            if ($line === '') {
                continue;
            }
            $rows[] = ['ai_note_' . ($idx + 1), $line];
        }

        if (count($rows) === 1) {
            return $fallbackText;
        }

        return $rows;
    }

    private function detectSheetMappings(array $tables): array
    {
        $fallback = $this->heuristicMappings($tables);
        $summaries = [];
        foreach ($tables as $index => $table) {
            $rows = $this->normalizeTableRows($table['rows'] ?? []);
            $headers = $rows[0] ?? [];
            $samples = array_slice($rows, 1, 3);
            $summaries[] = [
                'index' => $index,
                'sheet_name' => (string)($table['name'] ?? ('Sheet ' . ($index + 1))),
                'headers' => $headers,
                'sample_rows' => $samples,
            ];
        }

        $response = $this->llm->completeJson(
            'You map spreadsheet sheets to SQL catalog entities for a coffee shop admin import tool.',
            "Target entities:\n"
            . "- menu_items fields: " . implode(', ', self::ENTITY_FIELDS['menu_items']) . "\n"
            . "- menu_variants fields: " . implode(', ', self::ENTITY_FIELDS['menu_variants']) . "\n"
            . "- menu_toppings fields: " . implode(', ', self::ENTITY_FIELDS['menu_toppings']) . "\n"
            . "- branch_availability fields: " . implode(', ', self::ENTITY_FIELDS['branch_availability']) . "\n"
            . "- menu_item_toppings fields: " . implode(', ', self::ENTITY_FIELDS['menu_item_toppings']) . "\n"
            . "Return JSON array with one object per sheet: {sheet_name, entity, field_map, confidence, notes}. entity must be one of menu_items, menu_variants, menu_toppings, branch_availability, menu_item_toppings, ignore.\n"
            . "Spreadsheet summaries:\n" . json_encode($summaries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $fallback,
            1400
        );

        if ($this->isValidMappingsResponse($response, count($tables))) {
            return $response;
        }

        return $fallback;
    }

    private function heuristicMappings(array $tables): array
    {
        $mappings = [];
        foreach ($tables as $table) {
            $rows = $this->normalizeTableRows($table['rows'] ?? []);
            $headers = array_map([$this, 'normalizeHeader'], $rows[0] ?? []);
            $joined = implode(' ', $headers);
            $entity = 'ignore';

            if (str_contains($joined, 'variant_label') || str_contains($joined, 'variant_slug')) {
                $entity = 'menu_variants';
            } elseif (str_contains($joined, 'topping_name') || str_contains($joined, 'linked_items')) {
                $entity = 'menu_toppings';
            } elseif (str_contains($joined, 'branch_is_available') || (str_contains($joined, 'branch_price') && !str_contains($joined, 'global_price_idr'))) {
                $entity = 'branch_availability';
            } elseif (str_contains($joined, 'item_name') || str_contains($joined, 'global_price_idr') || str_contains($joined, 'category_name')) {
                $entity = 'menu_items';
            }

            $fieldMap = [];
            foreach ($rows[0] ?? [] as $header) {
                $normalized = $this->normalizeHeader((string)$header);
                $fieldMap[$normalized] = (string)$header;
            }

            $mappings[] = [
                'sheet_name' => (string)($table['name'] ?? 'Sheet'),
                'entity' => $entity,
                'field_map' => $fieldMap,
                'confidence' => $entity === 'ignore' ? 0.1 : 0.7,
                'notes' => 'heuristic',
            ];
        }

        return $mappings;
    }

    private function resolveMappings(array $tables, ?array $manualMappings): array
    {
        if (is_array($manualMappings) && $this->isValidMappingsResponse($manualMappings, count($tables))) {
            $normalized = [];
            foreach ($tables as $index => $table) {
                $rows = $this->normalizeTableRows($table['rows'] ?? []);
                $headers = $rows[0] ?? [];
                $sheetMapping = $manualMappings[$index] ?? [];
                $entity = (string)($sheetMapping['entity'] ?? 'ignore');
                if (!isset(self::ENTITY_FIELDS[$entity])) {
                    $entity = 'ignore';
                }
                $normalized[] = [
                    'sheet_name' => (string)($sheetMapping['sheet_name'] ?? ($table['name'] ?? 'Sheet')),
                    'entity' => $entity,
                    'field_map' => $this->normalizeFieldMap((array)($sheetMapping['field_map'] ?? []), $headers),
                    'confidence' => (float)($sheetMapping['confidence'] ?? 1.0),
                    'notes' => (string)($sheetMapping['notes'] ?? 'manual'),
                ];
            }
            return $normalized;
        }

        return $this->detectSheetMappings($tables);
    }

    private function normalizeTableRows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $normalized[] = array_map(static fn($cell): string => trim((string)$cell), (array)$row);
        }
        return $normalized;
    }

    private function assocRowsToSheetRows(array $rows, ?array $columns = null): array
    {
        $sheetRows = [];
        foreach ($rows as $row) {
            $values = [];
            $sourceColumns = $columns ?? array_keys($row);
            foreach ($sourceColumns as $column) {
                $value = $row[$column] ?? '';
                $values[] = is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $sheetRows[] = $values;
        }
        return $sheetRows;
    }

    private function buildSheetNoteRow(array $columns, string $message): array
    {
        $row = array_fill(0, count($columns), '');
        $row[0] = $message;
        return $row;
    }

    private function normalizeFieldMap(array $fieldMap, array $headers): array
    {
        $headerLookup = [];
        foreach ($headers as $header) {
            $headerLookup[$this->normalizeHeader((string)$header)] = (string)$header;
        }

        $normalized = [];
        foreach ($fieldMap as $canonical => $header) {
            $canonicalKey = $this->normalizeHeader((string)$canonical);
            $headerName = (string)$header;
            if ($headerName !== '' && isset($headerLookup[$this->normalizeHeader($headerName)])) {
                $normalized[$canonicalKey] = $headerLookup[$this->normalizeHeader($headerName)];
                continue;
            }
            if (isset($headerLookup[$canonicalKey])) {
                $normalized[$canonicalKey] = $headerLookup[$canonicalKey];
            }
        }

        return $normalized;
    }

    private function rowToAssoc(array $headers, array $row): array
    {
        $assoc = [];
        foreach ($headers as $index => $header) {
            $assoc[(string)$header] = trim((string)($row[$index] ?? ''));
        }
        return $assoc;
    }

    private function rowIsEmpty(array $assoc): bool
    {
        foreach ($assoc as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function toCanonicalRow(array $assoc, array $fieldMap): array
    {
        $canonical = [];
        foreach ($fieldMap as $canonicalKey => $header) {
            $canonical[$canonicalKey] = trim((string)($assoc[$header] ?? ''));
        }
        return $canonical;
    }

    private function importMenuItemRow(int $branchId, array $row, array &$summary, array &$itemsSheetOverridesTouched): bool
    {
        $name = $row['item_name'] ?? $row['name'] ?? '';
        if ($name === '') {
            return false;
        }

        $categoryName = $row['category_name'] ?? 'Lainnya';
        $categoryId = $this->resolveCategoryId($categoryName, $row['category_slug'] ?? '');
        $slug = $this->normalizeItemSlug($row['item_slug'] ?? '', $name, $branchId);
        $existingId = $this->resolveMenuItemId($name, $slug, $categoryId);
        $data = [
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'description' => $row['description'] ?? '',
            'price' => $this->parseNumber($row['global_price_idr'] ?? $row['price'] ?? '0'),
            'min_toppings' => $this->parseInt($row['min_toppings'] ?? '0'),
            'max_toppings' => $this->parseInt($row['max_toppings'] ?? '0'),
            'image_path' => $row['image_path'] ?? '',
            'is_available' => $this->parseBool($row['global_is_available'] ?? '1') ? 1 : 0,
            'is_active' => $this->parseBool($row['is_active'] ?? '1') ? 1 : 0,
            'sort_order' => $this->parseInt($row['sort_order'] ?? '0'),
        ];

        if ($existingId > 0) {
            $stmt = $this->db->prepare(
                'UPDATE menu_items
                 SET category_id=:category_id, name=:name, slug=:slug, description=:description, price=:price,
                     min_toppings=:min_toppings, max_toppings=:max_toppings, image_path=:image_path,
                     is_available=:is_available, is_active=:is_active, sort_order=:sort_order
                 WHERE id=:id'
            );
            $stmt->execute($data + ['id' => $existingId]);
            $summary['updated_items']++;
            $menuItemId = $existingId;
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO menu_items
                 (category_id, name, slug, description, price, min_toppings, max_toppings, image_path, is_available, is_active, sort_order)
                 VALUES (:category_id, :name, :slug, :description, :price, :min_toppings, :max_toppings, :image_path, :is_available, :is_active, :sort_order)'
            );
            $stmt->execute($data);
            $summary['inserted_items']++;
            $menuItemId = (int)$this->db->lastInsertId();
        }

        $branchPriceRaw = $row['branch_price'] ?? '';
        $branchAvailableRaw = $row['branch_is_available'] ?? '';
        $branchNote = $row['branch_note'] ?? '';
        if ($branchPriceRaw !== '' || $branchAvailableRaw !== '' || $branchNote !== '') {
            $this->upsertBranchMenuOverride(
                $branchId,
                $menuItemId,
                $branchPriceRaw !== '' ? $this->parseNumber($branchPriceRaw) : null,
                $branchAvailableRaw !== '' ? ($this->parseBool($branchAvailableRaw) ? 1 : 0) : null,
                $branchNote
            );
            $itemsSheetOverridesTouched[$menuItemId] = true;
            $summary['updated_availability']++;
        }

        return true;
    }

    private function importVariantRow(int $branchId, array $row, array &$summary): bool
    {
        $itemName = $row['item_name'] ?? '';
        $variantLabel = $row['variant_label'] ?? '';
        if ($itemName === '' || $variantLabel === '') {
            return false;
        }

        $variantCategoryId = trim((string)($row['category_name'] ?? '')) !== ''
            ? $this->resolveCategoryId((string)$row['category_name'], '')
            : 0;
        $menuItemId = $this->resolveMenuItemId(
            $itemName,
            $row['item_slug'] ?? '',
            $variantCategoryId
        );
        if ($menuItemId <= 0) {
            $summary['warnings'][] = 'Menu item untuk variant tidak ditemukan: ' . $itemName;
            return false;
        }

        $variantSlug = $this->normalizeVariantSlug($row['variant_slug'] ?? '', $variantLabel);
        $existingId = $this->resolveVariantId($menuItemId, $variantLabel, $variantSlug);
        $data = [
            'menu_item_id' => $menuItemId,
            'label' => $variantLabel,
            'slug' => $variantSlug,
            'price_delta' => $this->parseNumber($row['global_delta_idr'] ?? $row['price_delta'] ?? '0'),
            'sort_order' => $this->parseInt($row['sort_order'] ?? '0'),
            'is_active' => $this->parseBool($row['is_active'] ?? '1') ? 1 : 0,
        ];

        if ($existingId > 0) {
            $updateData = $data;
            unset($updateData['menu_item_id']);
            $stmt = $this->db->prepare(
                'UPDATE menu_item_variants
                 SET label=:label, slug=:slug, price_delta=:price_delta, sort_order=:sort_order, is_active=:is_active
                 WHERE id=:id'
            );
            $stmt->execute($updateData + ['id' => $existingId]);
            $summary['updated_variants']++;
            $variantId = $existingId;
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO menu_item_variants
                 (menu_item_id, label, slug, price_delta, sort_order, is_active)
                 VALUES (:menu_item_id, :label, :slug, :price_delta, :sort_order, :is_active)'
            );
            $stmt->execute($data);
            $summary['inserted_variants']++;
            $variantId = (int)$this->db->lastInsertId();
        }

        if (($row['branch_delta'] ?? '') !== '') {
            $stmt = $this->db->prepare(
                'INSERT INTO branch_menu_variant_overrides (branch_id, variant_id, price_delta, is_active)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE price_delta=VALUES(price_delta), is_active=1'
            );
            $stmt->execute([$branchId, $variantId, $this->parseNumber($row['branch_delta'])]);
        }

        return true;
    }

    private function importToppingRow(array $row, array &$summary): bool
    {
        $name = $row['topping_name'] ?? '';
        if ($name === '') {
            return false;
        }

        $slug = $this->normalizeVariantSlug($row['topping_slug'] ?? '', $name);
        $existingId = $this->resolveToppingId($name, $slug);
        $data = [
            'name' => $name,
            'slug' => $slug,
            'price_delta' => $this->parseNumber($row['price_delta'] ?? '0'),
            'sort_order' => $this->parseInt($row['sort_order'] ?? '0'),
            'is_active' => $this->parseBool($row['is_active'] ?? '1') ? 1 : 0,
        ];

        if ($existingId > 0) {
            $stmt = $this->db->prepare(
                'UPDATE menu_toppings
                 SET name=:name, slug=:slug, price_delta=:price_delta, sort_order=:sort_order, is_active=:is_active
                 WHERE id=:id'
            );
            $stmt->execute($data + ['id' => $existingId]);
            $summary['updated_toppings']++;
            $toppingId = $existingId;
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO menu_toppings (name, slug, price_delta, sort_order, is_active)
                 VALUES (:name, :slug, :price_delta, :sort_order, :is_active)'
            );
            $stmt->execute($data);
            $summary['inserted_toppings']++;
            $toppingId = (int)$this->db->lastInsertId();
        }

        $linkedItems = trim((string)($row['linked_items'] ?? ''));
        if ($linkedItems !== '') {
            foreach (preg_split('/\s*[|,;]\s*/', $linkedItems) ?: [] as $itemName) {
                $itemName = trim($itemName);
                if ($itemName === '') {
                    continue;
                }
                $menuItemId = $this->resolveMenuItemId($itemName, '', 0);
                if ($menuItemId > 0) {
                    $stmt = $this->db->prepare(
                        'INSERT IGNORE INTO menu_item_toppings (menu_item_id, topping_id, sort_order) VALUES (?, ?, ?)'
                    );
                    $stmt->execute([$menuItemId, $toppingId, 0]);
                    $summary['linked_toppings']++;
                }
            }
        }

        return true;
    }

    private function importAvailabilityRow(int $branchId, array $row, array &$summary, array $itemsSheetOverridesTouched): bool
    {
        $itemName = $row['item_name'] ?? '';
        if ($itemName === '') {
            return false;
        }

        $availabilityCategoryId = trim((string)($row['category_name'] ?? '')) !== ''
            ? $this->resolveCategoryId((string)$row['category_name'], '')
            : 0;
        $menuItemId = $this->resolveMenuItemId(
            $itemName,
            $row['item_slug'] ?? '',
            $availabilityCategoryId
        );
        if ($menuItemId <= 0) {
            return false;
        }
        if (isset($itemsSheetOverridesTouched[$menuItemId])) {
            $summary['warnings'][] = 'Availability sheet dilewati untuk "' . $itemName . '" karena branch override sudah diambil dari sheet Items.';
            return true;
        }

        $branchPrice = ($row['branch_price'] ?? '') !== '' ? $this->parseNumber($row['branch_price']) : null;
        $available = ($row['branch_is_available'] ?? '') !== '' ? ($this->parseBool($row['branch_is_available']) ? 1 : 0) : null;
        $this->upsertBranchMenuOverride($branchId, $menuItemId, $branchPrice, $available, $row['note'] ?? '');
        $summary['updated_availability']++;
        return true;
    }

    private function importItemToppingLinkRow(array $row, array &$summary): bool
    {
        $itemName = $row['item_name'] ?? '';
        $toppingName = $row['topping_name'] ?? '';
        if ($itemName === '' || $toppingName === '') {
            return false;
        }

        $menuItemId = $this->resolveMenuItemId($itemName, $row['item_slug'] ?? '', 0);
        $toppingId = $this->resolveToppingId($toppingName, $row['topping_slug'] ?? '');
        if ($menuItemId <= 0 || $toppingId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO menu_item_toppings (menu_item_id, topping_id, sort_order)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order)'
        );
        $stmt->execute([$menuItemId, $toppingId, $this->parseInt($row['sort_order'] ?? '0')]);
        $summary['linked_toppings']++;
        return true;
    }

    private function upsertBranchMenuOverride(int $branchId, int $menuItemId, ?float $customPrice, ?int $isAvailable, string $note): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO branch_menu_overrides (branch_id, menu_item_id, custom_price, is_available, note)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE custom_price=VALUES(custom_price), is_available=VALUES(is_available), note=VALUES(note)'
        );
        $stmt->execute([$branchId, $menuItemId, $customPrice, $isAvailable, trim($note) !== '' ? trim($note) : null]);
    }

    private function resolveCategoryId(string $name, string $slug): int
    {
        $name = trim($name) !== '' ? trim($name) : 'Lainnya';
        $slug = trim($slug) !== '' ? trim($slug) : Sanitize::slug($name);

        $stmt = $this->db->prepare('SELECT id FROM menu_categories WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $found = $stmt->fetchColumn();
        if ($found) {
            return (int)$found;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO menu_categories (name, slug, description, sort_order, is_active)
             VALUES (?, ?, NULL, 0, 1)'
        );
        $stmt->execute([$name, $slug]);
        return (int)$this->db->lastInsertId();
    }

    private function resolveMenuItemId(string $name, string $slug, int $categoryId): int
    {
        if (trim($slug) !== '') {
            $stmt = $this->db->prepare('SELECT id FROM menu_items WHERE slug = ? LIMIT 1');
            $stmt->execute([trim($slug)]);
            $found = $stmt->fetchColumn();
            if ($found) {
                return (int)$found;
            }
        }

        $sql = 'SELECT id FROM menu_items WHERE LOWER(name) = LOWER(?)';
        $params = [trim($name)];
        if ($categoryId > 0) {
            $sql .= ' AND category_id = ?';
            $params[] = $categoryId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $found = $stmt->fetchColumn();
        return $found ? (int)$found : 0;
    }

    private function resolveVariantId(int $menuItemId, string $label, string $slug): int
    {
        if ($slug !== '') {
            $stmt = $this->db->prepare('SELECT id FROM menu_item_variants WHERE menu_item_id = ? AND slug = ? LIMIT 1');
            $stmt->execute([$menuItemId, $slug]);
            $found = $stmt->fetchColumn();
            if ($found) {
                return (int)$found;
            }
        }

        $stmt = $this->db->prepare('SELECT id FROM menu_item_variants WHERE menu_item_id = ? AND LOWER(label) = LOWER(?) LIMIT 1');
        $stmt->execute([$menuItemId, $label]);
        $found = $stmt->fetchColumn();
        return $found ? (int)$found : 0;
    }

    private function resolveToppingId(string $name, string $slug): int
    {
        if (trim($slug) !== '') {
            $stmt = $this->db->prepare('SELECT id FROM menu_toppings WHERE slug = ? LIMIT 1');
            $stmt->execute([trim($slug)]);
            $found = $stmt->fetchColumn();
            if ($found) {
                return (int)$found;
            }
        }

        $stmt = $this->db->prepare('SELECT id FROM menu_toppings WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->execute([$name]);
        $found = $stmt->fetchColumn();
        return $found ? (int)$found : 0;
    }

    private function normalizeItemSlug(string $slug, string $name, int $branchId): string
    {
        $slug = trim($slug);
        if ($slug !== '') {
            return $slug;
        }
        return Sanitize::slug($name) . '-' . $branchId;
    }

    private function normalizeVariantSlug(string $slug, string $label): string
    {
        $slug = trim($slug);
        return $slug !== '' ? $slug : Sanitize::slug($label);
    }

    private function parseNumber(string $value): float
    {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }

        $value = preg_replace('/[^0-9,.\-]/', '', $value) ?? $value;
        if (substr_count($value, ',') > 0 && substr_count($value, '.') > 0) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, ',') > 0 && substr_count($value, '.') === 0) {
            $value = str_replace(',', '.', $value);
        }
        return (float)$value;
    }

    private function parseInt(string $value): int
    {
        return (int)round($this->parseNumber($value));
    }

    private function parseBool(string $value): bool
    {
        $value = strtolower(trim($value));
        return in_array($value, ['1', 'true', 'yes', 'ya', 'aktif', 'available', 'tersedia', 'on'], true);
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;
        return trim($header, '_');
    }

    private function isValidMappingsResponse(array $response, int $expectedCount): bool
    {
        if (count($response) !== $expectedCount) {
            return false;
        }
        foreach ($response as $sheet) {
            if (!is_array($sheet) || !isset($sheet['entity'])) {
                return false;
            }
            $entity = (string)$sheet['entity'];
            if (!isset(self::ENTITY_FIELDS[$entity])) {
                return false;
            }
        }
        return true;
    }
}
