<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\{Auth, Response, Sanitize};
use App\Config\Database;

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$user     = Auth::user();
$branchId = Auth::isSuperAdmin() ? (int)($_POST['branch_id'] ?? 0) : (int)$user['branch_id'];

if (!$branchId) Response::error('branch_id required');
if (!Auth::canAccessBranch($branchId)) Response::error('Access denied', 403);

$file = $_FILES['file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) Response::error('File upload failed');

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') Response::error('Only CSV allowed for promo upload');

$destDir  = UPLOAD_PATH . '/promo/';
$destFile = $destDir . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
if (!move_uploaded_file($file['tmp_name'], $destFile)) Response::error('Failed to save file');

$db       = Database::getInstance();
$imported = 0;
$errors   = [];

$handle = fopen($destFile, 'r');
$header = null;
while (($row = fgetcsv($handle, 1000, ',')) !== false) {
    if (!$header) { $header = $row; continue; }
    $data = array_combine($header, $row);

    if (empty($data['title'])) {
        $errors[] = 'Skipped: missing title';
        continue;
    }

    $db->prepare(
        'INSERT INTO branch_promos (branch_id, title, description, discount_type, discount_value, min_order, promo_code, start_date, end_date, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
    )->execute([
        $branchId,
        Sanitize::string($data['title']),
        Sanitize::string($data['description'] ?? ''),
        in_array($data['discount_type'] ?? 'percent', ['percent','fixed']) ? $data['discount_type'] : 'percent',
        (float)($data['discount_value'] ?? 0),
        (float)($data['min_order']      ?? 0),
        Sanitize::string($data['promo_code'] ?? ''),
        !empty($data['start_date']) ? $data['start_date'] : null,
        !empty($data['end_date'])   ? $data['end_date']   : null,
    ]);
    $imported++;
}
fclose($handle);

Response::success(['imported' => $imported, 'errors' => $errors], 'Promo data processed');
