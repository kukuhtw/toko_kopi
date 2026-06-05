<?php

declare(strict_types=1);

namespace KopiBot\Core;

use KopiBot\Domains\Auth\JwtService;

class AuthMiddleware
{
    public function __construct(
        private JwtService $jwt = new JwtService()
    ) {}

    public function user(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        try {
            return $this->jwt->decode($token);
        } catch (\Throwable) {
            return null;
        }
    }
}
