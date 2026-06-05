<?php

declare(strict_types=1);

namespace KopiBot\Domains\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;

    public function __construct(?string $secret = null)
    {
        $this->secret = $secret ?: (string) env_value('JWT_SECRET', 'change-this-secret');
    }

    public function issue(array $claims, int $ttlSeconds = 86400): string
    {
        $now = time();
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
