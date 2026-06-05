<?php

declare(strict_types=1);

namespace KopiBot\Domains\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use KopiBot\Core\Config;

class JwtService
{
    private string $secret;

    public function __construct(?string $secret = null)
    {
        $this->secret = $secret ?: (string) Config::get('JWT_SECRET', 'change-this-secret');
    }

    public function issue(array $claims, ?int $ttlSeconds = null): string
    {
        $now = time();
        $ttlSeconds = $ttlSeconds ?? Config::int('JWT_TTL_SECONDS', 86400);
        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ]);

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function decode(string $token): array
    {
        return (array) JWT::decode($token, new Key($this->secret, 'HS256'));
    }
}
