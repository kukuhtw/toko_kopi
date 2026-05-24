<?php

declare(strict_types=1);

namespace App\Helpers;

use RuntimeException;

class MenuImage
{
    private const DIRECTORY = 'menu-items';
    private const MAX_SIZE = 5242880; // 5 MB
    private const NORMALIZED_WIDTH = 960;
    private const NORMALIZED_HEIGHT = 960;
    private const JPEG_QUALITY = 82;
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

        return self::storeNormalizedFile($tmpName, $slugSeed, $mimeType, $extension, true);
    }

    public static function storeGeneratedBinary(string $binary, string $slugSeed, string $extension = 'png'): string
    {
        $extension = strtolower(trim($extension));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('Format gambar AI tidak didukung.');
        }

        $mimeType = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return self::storeNormalizedBinary($binary, $slugSeed, $mimeType, $extension);
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

    private static function storeNormalizedFile(
        string $sourcePath,
        string $slugSeed,
        string $mimeType,
        string $extension,
        bool $isUpload = false
    ): string {
        if (!extension_loaded('gd')) {
            return self::storeOriginalFileWithoutResize($sourcePath, $slugSeed, $extension, $isUpload);
        }

        $binary = @file_get_contents($sourcePath);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('Foto produk gagal dibaca.');
        }

        return self::storeNormalizedBinary($binary, $slugSeed, $mimeType, $extension);
    }

    private static function storeNormalizedBinary(string $binary, string $slugSeed, string $mimeType, string $extension): string
    {
        if (!extension_loaded('gd')) {
            return self::storeOriginalBinaryWithoutResize($binary, $slugSeed, $extension);
        }

        $source = self::createImageFromBinary($binary, $mimeType);
        if (!$source) {
            throw new RuntimeException('Gagal memproses gambar produk.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($source);
            throw new RuntimeException('Dimensi gambar produk tidak valid.');
        }

        $cropSize = min($width, $height);
        $srcX = (int) floor(($width - $cropSize) / 2);
        $srcY = (int) floor(($height - $cropSize) / 2);

        $target = imagecreatetruecolor(self::NORMALIZED_WIDTH, self::NORMALIZED_HEIGHT);
        if (!$target) {
            imagedestroy($source);
            throw new RuntimeException('Gagal menyiapkan kanvas gambar produk.');
        }

        $outputExtension = 'jpg';
        $background = imagecolorallocate($target, 255, 255, 255);
        imagefilledrectangle($target, 0, 0, self::NORMALIZED_WIDTH, self::NORMALIZED_HEIGHT, $background);

        if (!imagecopyresampled(
            $target,
            $source,
            0,
            0,
            $srcX,
            $srcY,
            self::NORMALIZED_WIDTH,
            self::NORMALIZED_HEIGHT,
            $cropSize,
            $cropSize
        )) {
            imagedestroy($target);
            imagedestroy($source);
            throw new RuntimeException('Gagal resize gambar produk.');
        }

        $relativePath = self::buildRelativePath($slugSeed, $outputExtension);
        $absolutePath = self::absolutePath($relativePath);

        imageinterlace($target, true);
        $saved = imagejpeg($target, $absolutePath, self::JPEG_QUALITY);

        imagedestroy($target);
        imagedestroy($source);

        if (!$saved) {
            throw new RuntimeException('Foto produk gagal disimpan.');
        }

        return $relativePath;
    }

    private static function createImageFromBinary(string $binary, string $mimeType)
    {
        return match ($mimeType) {
            'image/jpeg', 'image/png', 'image/webp', 'image/gif' => @imagecreatefromstring($binary),
            default => null,
        };
    }

    private static function storeOriginalFileWithoutResize(
        string $sourcePath,
        string $slugSeed,
        string $extension,
        bool $isUpload = false
    ): string {
        $relativePath = self::buildRelativePath($slugSeed, $extension);
        $absolutePath = self::absolutePath($relativePath);

        $written = $isUpload
            ? move_uploaded_file($sourcePath, $absolutePath)
            : copy($sourcePath, $absolutePath);

        if (!$written) {
            throw new RuntimeException('Foto produk gagal disimpan.');
        }

        return $relativePath;
    }

    private static function storeOriginalBinaryWithoutResize(string $binary, string $slugSeed, string $extension): string
    {
        $relativePath = self::buildRelativePath($slugSeed, $extension);
        $absolutePath = self::absolutePath($relativePath);
        if (file_put_contents($absolutePath, $binary) === false) {
            throw new RuntimeException('Foto produk AI gagal disimpan.');
        }

        return $relativePath;
    }

    private static function buildRelativePath(string $slugSeed, string $extension): string
    {
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

        return self::DIRECTORY . '/' . $fileName;
    }

    private static function absolutePath(string $relativePath): string
    {
        return rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }
}
