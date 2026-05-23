<?php

declare(strict_types=1);

namespace App\Helpers;

class Csrf
{
    private const EXPLANATION = 'Sesi keamanan formulir tidak cocok atau sudah kedaluwarsa.';
    private const ACTIONS = [
        'Muat ulang halaman ini lalu coba kirim form lagi.',
        'Jika membuka tab terlalu lama, login ulang lalu ulangi prosesnya.',
        'Hindari submit ulang dari halaman lama setelah logout atau timeout sesi.',
    ];

    public static function generate(): string
    {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function verify(string $token, bool $rotate = true): bool
    {
        $stored = $_SESSION[CSRF_TOKEN_NAME] ?? '';
        if (empty($stored) || !hash_equals($stored, $token)) {
            return false;
        }
        if ($rotate) {
            unset($_SESSION[CSRF_TOKEN_NAME]);
        }
        return true;
    }

    public static function requestToken(): string
    {
        return (string)($_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }

    public static function isValidRequest(bool $rotate = true): bool
    {
        return self::verify(self::requestToken(), $rotate);
    }

    public static function field(): string
    {
        $token = self::generate();
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars(CSRF_TOKEN_NAME),
            htmlspecialchars($token)
        );
    }

    public static function requireValid(bool $rotate = true): void
    {
        if (!self::isValidRequest($rotate)) {
            http_response_code(403);
            self::respondInvalidToken();
        }
    }

    private static function respondInvalidToken(): never
    {
        $message = 'Invalid CSRF token.';
        $details = [
            'meaning' => self::EXPLANATION,
            'actions' => self::ACTIONS,
        ];

        if (self::expectsJson()) {
            Response::error($message, 403, $details);
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Invalid CSRF token</title>'
            . '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f6f7fb;color:#18212f;padding:32px;line-height:1.6}'
            . '.card{max-width:760px;margin:0 auto;background:#fff;border:1px solid #d8e0ea;border-radius:14px;padding:24px;box-shadow:0 8px 24px rgba(0,0,0,.06)}'
            . 'h1{margin:0 0 12px;font-size:28px}.muted{color:#5b6778}.box{background:#eef7fb;border:1px solid #cfe8f3;border-radius:10px;padding:14px;margin:18px 0}'
            . 'ul{margin:10px 0 0 20px;padding:0}a{color:#0b78b5}</style></head><body><div class="card">'
            . '<h1>Invalid CSRF token</h1>'
            . '<p class="muted">Permintaan diblokir untuk mencegah submit form yang tidak sah.</p>'
            . '<div class="box"><strong>Apa artinya?</strong><br>' . htmlspecialchars(self::EXPLANATION) . '</div>'
            . '<div class="box"><strong>Apa yang harus dilakukan?</strong><ul>'
            . '<li>' . htmlspecialchars(self::ACTIONS[0]) . '</li>'
            . '<li>' . htmlspecialchars(self::ACTIONS[1]) . '</li>'
            . '<li>' . htmlspecialchars(self::ACTIONS[2]) . '</li>'
            . '</ul></div>'
            . '<p><a href="javascript:history.back()">Kembali ke halaman sebelumnya</a></p>'
            . '</div></body></html>';
        exit;
    }

    private static function expectsJson(): bool
    {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        $uri = strtolower((string)($_SERVER['REQUEST_URI'] ?? ''));

        return str_contains($accept, 'application/json')
            || $requestedWith === 'xmlhttprequest'
            || str_contains($contentType, 'application/json')
            || str_contains($uri, '/api/');
    }
}
