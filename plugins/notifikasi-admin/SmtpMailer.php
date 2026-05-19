<?php

declare(strict_types=1);

/**
 * SMTP mailer minimal — tanpa dependency eksternal.
 * Mendukung: plain, STARTTLS (port 587), SSL (port 465).
 * Auth: AUTH LOGIN.
 */
class SmtpMailer
{
    public static function send(array $cfg, string $to, string $subject, string $body): bool
    {
        $host       = (string)($cfg['smtp_host']       ?? '');
        $port       = (int)   ($cfg['smtp_port']       ?? 587);
        $encryption = (string)($cfg['smtp_encryption'] ?? 'tls');
        $user       = (string)($cfg['smtp_user']       ?? '');
        $pass       = (string)($cfg['smtp_pass']       ?? '');
        $fromEmail  = (string)($cfg['smtp_from_email'] ?? $user);
        $fromName   = (string)($cfg['smtp_from_name']  ?? 'KopiBot');

        if ($host === '' || $to === '') {
            return false;
        }

        $prefix = $encryption === 'ssl' ? 'ssl://' : '';
        $socket = @stream_socket_client("{$prefix}{$host}:{$port}", $errno, $errstr, 15);
        if (!$socket) {
            error_log("[SmtpMailer] Gagal konek ke {$host}:{$port} — {$errstr}");
            return false;
        }

        stream_set_timeout($socket, 15);

        try {
            // Greeting
            self::read($socket);

            // EHLO
            self::cmd($socket, "EHLO localhost");
            self::readMultiline($socket);

            // STARTTLS upgrade
            if ($encryption === 'tls') {
                $resp = self::cmd($socket, 'STARTTLS');
                if (!str_starts_with($resp, '220')) {
                    throw new \RuntimeException("STARTTLS ditolak: {$resp}");
                }
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('TLS handshake gagal.');
                }
                self::cmd($socket, 'EHLO localhost');
                self::readMultiline($socket);
            }

            // AUTH LOGIN
            if ($user !== '') {
                self::cmd($socket, 'AUTH LOGIN');
                self::cmd($socket, base64_encode($user));
                $resp = self::cmd($socket, base64_encode($pass));
                if (!str_starts_with($resp, '235')) {
                    throw new \RuntimeException("Auth gagal: {$resp}");
                }
            }

            // Envelope
            $resp = self::cmd($socket, "MAIL FROM:<{$fromEmail}>");
            if (!str_starts_with($resp, '250')) {
                throw new \RuntimeException("MAIL FROM ditolak: {$resp}");
            }

            $resp = self::cmd($socket, "RCPT TO:<{$to}>");
            if (!str_starts_with($resp, '250')) {
                throw new \RuntimeException("RCPT TO ditolak: {$resp}");
            }

            // Data
            self::cmd($socket, 'DATA');

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFrom    = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
            $message        = "From: {$encodedFrom} <{$fromEmail}>\r\n"
                            . "To: {$to}\r\n"
                            . "Subject: {$encodedSubject}\r\n"
                            . "MIME-Version: 1.0\r\n"
                            . "Content-Type: text/plain; charset=UTF-8\r\n"
                            . "Content-Transfer-Encoding: base64\r\n"
                            . "Date: " . date('r') . "\r\n"
                            . "\r\n"
                            . chunk_split(base64_encode($body));

            fwrite($socket, $message . "\r\n.\r\n");
            $resp = self::read($socket);
            if (!str_starts_with($resp, '250')) {
                throw new \RuntimeException("Pesan ditolak: {$resp}");
            }

            self::cmd($socket, 'QUIT');
            return true;

        } catch (\Throwable $e) {
            error_log('[SmtpMailer] ' . $e->getMessage());
            return false;
        } finally {
            fclose($socket);
        }
    }

    /** Kirim perintah dan baca satu baris respons. */
    private static function cmd($socket, string $command): string
    {
        fwrite($socket, $command . "\r\n");
        return self::read($socket);
    }

    /** Baca satu baris respons. */
    private static function read($socket): string
    {
        return (string)fgets($socket, 512);
    }

    /** Baca respons multi-baris EHLO (baris diakhiri ' ' bukan '-' setelah kode). */
    private static function readMultiline($socket): void
    {
        while (($line = fgets($socket, 512)) !== false) {
            if (substr($line, 3, 1) !== '-') break;
        }
    }
}
