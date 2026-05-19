<?php

declare(strict_types=1);

namespace App\Plugin;

interface PluginInterface
{
    public function getName(): string;
    public function getVersion(): string;
    public function getAuthor(): string;

    /**
     * Daftarkan semua action dan filter plugin di sini.
     * Dipanggil otomatis oleh PluginLoader saat bootstrap.
     */
    public function register(): void;
}
