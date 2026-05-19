<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\{Auth, Response};
use App\Models\{MenuModel};
use App\Config\Database;

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$user     = Auth::user();
$branchId = Auth::isSuperAdmin() ? (int)($_POST['branch_id'] ?? 0) : (int)$user['branch_id'];

if (!$branchId) Response::error('branch_id required');
if (!Auth::canAccessBranch($branchId)) Response::error('Access denied', 403);

$file = $_FILES['file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    Response::error('File upload failed');
}

$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed  = ['csv', 'xlsx', 'xls'];
if (!in_array($ext, $allowed)) {
    Response::error('Only CSV, XLS, XLSX files allowed');
}

if ($file['size'] > 5 * 1024 * 1024) {
    Response::error('File size must be under 5MB');
}

$destDir  = UPLOAD_PATH . '/menu/';
$destFile = $destDir . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
if (!move_uploaded_file($file['tmp_name'], $destFile)) {
    Response::error('Failed to save file');
}

// Log upload
$db = Database::getInstance();
$db->prepare(
    'INSERT INTO uploaded_files (branch_id, uploaded_by, file_type, original_name, stored_path, file_size, mime_type)
     VALUES (?, ?, "menu", ?, ?, ?, ?)'
)->execute([$branchId, $user['id'], $file['name'], $destFile, $file['size'], $file['type']]);

// Process CSV
$imported = 0;
$errors   = [];

if ($ext === 'csv') {
    $handle = fopen($destFile, 'r');
    $header = null;
    $menuModel    = new MenuModel();
    $db_conn      = Database::getInstance();

    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
        if (!$header) { $header = $row; continue; }
        $data = array_combine($header, $row);

        // Expected columns: category_name, name, description, price
        if (empty($data['name']) || !is_numeric($data['price'] ?? '')) {
            $errors[] = "Row skipped: " . implode(', ', $row);
            continue;
        }

        // Find or create category
        $catSlug = \App\Helpers\Sanitize::slug($data['category_name'] ?? 'lainnya');
        $cat = $db_conn->prepare('SELECT id FROM menu_categories WHERE slug = ? LIMIT 1');
        $cat->execute([$catSlug]);
        $category = $cat->fetch();

        if (!$category) {
            $db_conn->prepare(
                'INSERT INTO menu_categories (name, slug) VALUES (?, ?)'
            )->execute([$data['category_name'], $catSlug]);
            $categoryId = (int) $db_conn->lastInsertId();
        } else {
            $categoryId = (int)$category['id'];
        }

        $slug = \App\Helpers\Sanitize::slug($data['name']);
        $db_conn->prepare(
            'INSERT INTO menu_items (category_id, name, slug, description, price)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE description = VALUES(description), price = VALUES(price)'
        )->execute([
            $categoryId,
            trim($data['name']),
            $slug,
            trim($data['description'] ?? ''),
            (float)$data['price'],
        ]);

        $imported++;
    }
    fclose($handle);
}

Response::success([
    'imported' => $imported,
    'errors'   => $errors,
    'file'     => basename($destFile),
], 'Menu data processed');
