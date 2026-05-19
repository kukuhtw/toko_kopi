<?php

declare(strict_types=1);

namespace App\Plugin;

/**
 * Kontrak untuk channel baru yang ditambahkan via plugin.
 *
 * Implementasikan interface ini untuk menambah channel pesan baru
 * (LINE, Slack, Instagram, Signal, dll.) tanpa mengubah kode inti.
 *
 * Cara mendaftarkan channel:
 *   HookManager::addFilter('channel.registered', function(array $channels): array {
 *       $channels['line'] = new LineChannelProvider($config);
 *       return $channels;
 *   });
 *
 * Webhook URL yang digunakan:
 *   POST /api/channel/webhook.php?channel={nama}&branch={id}
 */
interface ChannelInterface
{
    /** Identifier unik channel — huruf kecil, contoh: 'line', 'slack', 'signal' */
    public function getName(): string;

    /**
     * Verifikasi webhook dari platform (signature, token, dll.).
     * Kembalikan false untuk menolak request.
     *
     * @param array  $headers  HTTP headers (dari getallheaders())
     * @param string $rawBody  Raw request body sebelum di-decode
     */
    public function verifyWebhook(array $headers, string $rawBody): bool;

    /**
     * Parse payload webhook menjadi data pesan yang terstandar.
     * Kembalikan null jika payload tidak mengandung pesan yang perlu diproses.
     *
     * @return array{from: string, message: string}|null
     */
    public function parseMessage(array $payload): ?array;

    /**
     * Kirim pesan teks ke penerima lewat channel ini.
     *
     * @param string $recipient  Identifier penerima (user ID, chat ID, dll.)
     * @param string $message    Teks yang akan dikirim
     * @param array  $options    Opsi tambahan (tombol, media, format, dll.)
     */
    public function sendMessage(string $recipient, string $message, array $options = []): bool;

    /** Cek apakah channel ini sudah dikonfigurasi dan siap untuk branch tertentu */
    public function isAvailable(int $branchId): bool;
}
