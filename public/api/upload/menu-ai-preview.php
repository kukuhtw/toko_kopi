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
$destFile = $destDir . time() . '_preview_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
if (!move_uploaded_file($file['tmp_name'], $destFile)) {
    Response::error('Failed to save file');
}

$db = Database::getInstance();
$db->prepare(
    'INSERT INTO uploaded_files (branch_id, uploaded_by, file_type, original_name, stored_path, file_size, mime_type)
     VALUES (?, ?, "menu_ai_preview", ?, ?, ?, ?)'
)->execute([$branchId, $user['id'], $file['name'], $destFile, $file['size'], $file['type']]);
$uploadId = (int)$db->lastInsertId();

try {
    $service = new MenuCatalogAiTransferService();
    $preview = $service->previewBranchWorkbook($branchId, $destFile, $file['name']);
    $preview['uploaded_file_id'] = $uploadId;
    Response::success($preview, 'Preview AI import berhasil dibuat');
} catch (\Throwable $e) {
    error_log('[menu-ai-preview] ' . $e->getMessage());
    Response::error('AI preview gagal: ' . $e->getMessage(), 500);
}
