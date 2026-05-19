<?php

declare(strict_types=1);

namespace App\Helpers;

use RuntimeException;

class MenuImage
{
    private const DIRECTORY = 'menu-items';
    private const MAX_SIZE = 5242880; // 5 MB
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    public static function uploadFromRequest(string $fieldName, string $slugSeed): ?string
    {
        $file = $_FILES[$fieldName] ?? null;
        if (!$file || !is_array($file)) {
            return null;
        }

        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload foto gagal diproses.');
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('File foto tidak valid.');
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_SIZE) {
            throw new RuntimeException('Ukuran foto maksimal 5 MB.');
        }

        $mimeType = (string)(mime_content_type($tmpName) ?: '');
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;
        if ($extension === null) {
            throw new RuntimeException('Format foto harus JPG, PNG, WEBP, atau GIF.');
        }

        $targetDir = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . self::DIRECTORY;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Folder upload foto tidak bisa dibuat.');
        }

        $safeSlug = Sanitize::slug($slugSeed);
        if ($safeSlug === '') {
            $safeSlug = 'menu-item';
        }

        $fileName = sprintf(
            '%s-%s-%s.%s',
            $safeSlug,
            date('YmdHis'),
            substr(bin2hex(random_bytes(4)), 0, 8),
            $extension
        );

        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('Foto produk gagal disimpan.');
        }

        return self::DIRECTORY . '/' . $fileName;
    }

    public static function deleteManaged(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }

        $normalized = str_replace('\\', '/', $relativePath);
        if (!str_starts_with($normalized, self::DIRECTORY . '/')) {
            return;
        }

        $absolutePath = realpath(rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized));
        $baseDir = realpath(rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . self::DIRECTORY);

        if (!$absolutePath || !$baseDir || !str_starts_with($absolutePath, $baseDir . DIRECTORY_SEPARATOR)) {
            return;
        }

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    public static function publicUrl(?string $relativePath): ?string
    {
        if (!$relativePath) {
            return null;
        }

        $base = str_ends_with(BASE_URL, '/public')
            ? substr(BASE_URL, 0, -7)
            : BASE_URL;

        return rtrim($base, '/') . '/uploads/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }
}
