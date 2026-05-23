<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Config\Database;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Services\MenuCatalogAiTransferService;

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

if (!Csrf::isValidRequest(false)) {
    Response::error('Invalid CSRF token', 403);
}

$user = Auth::user();
$branchId = Auth::isSuperAdmin() ? (int)($_POST['branch_id'] ?? 0) : (int)$user['branch_id'];
if (!$branchId) {
    Response::error('branch_id required');
}
if (!Auth::canAccessBranch($branchId)) {
    Response::error('Access denied', 403);
}

$db = Database::getInstance();
$destFile = '';
$originalName = '';

$uploadedFileId = (int)($_POST['uploaded_file_id'] ?? 0);
if ($uploadedFileId > 0) {
    $stmt = $db->prepare(
        'SELECT * FROM uploaded_files WHERE id = ? AND branch_id = ? AND uploaded_by = ? LIMIT 1'
    );
    $stmt->execute([$uploadedFileId, $branchId, (int)$user['id']]);
    $uploaded = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$uploaded) {
        Response::error('Preview file tidak ditemukan atau tidak bisa diakses', 404);
    }
    $destFile = (string)($uploaded['stored_path'] ?? '');
    $originalName = (string)($uploaded['original_name'] ?? '');
    if ($destFile === '' || !is_file($destFile)) {
        Response::error('File preview sudah tidak tersedia', 404);
    }
} else {
    $file = $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        Response::error('File upload failed');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
        Response::error('Only CSV, XLSX, XLS files allowed');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        Response::error('File size must be under 10MB');
    }

    $destDir = UPLOAD_PATH . '/menu/';
    $destFile = $destDir . time() . '_ai_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    if (!move_uploaded_file($file['tmp_name'], $destFile)) {
        Response::error('Failed to save file');
    }

    $db->prepare(
        'INSERT INTO uploaded_files (branch_id, uploaded_by, file_type, original_name, stored_path, file_size, mime_type)
         VALUES (?, ?, "menu_ai", ?, ?, ?, ?)'
    )->execute([$branchId, $user['id'], $file['name'], $destFile, $file['size'], $file['type']]);
    $originalName = $file['name'];
}

try {
    $service = new MenuCatalogAiTransferService();
    $mappingJson = trim((string)($_POST['mapping_json'] ?? ''));
    $manualMappings = null;
    if ($mappingJson !== '') {
        $decoded = json_decode($mappingJson, true);
        if (is_array($decoded)) {
            $manualMappings = $decoded;
        }
    }

    $summary = $service->importBranchWorkbook($branchId, $destFile, $originalName, $manualMappings);
    Response::success($summary, 'AI import menu selesai diproses');
} catch (\Throwable $e) {
    error_log('[menu-ai-import] ' . $e->getMessage());
    Response::error('AI import gagal: ' . $e->getMessage(), 500);
}
