<?php

declare(strict_types=1);

namespace KopiBot\Core;

class ProtectedRouter extends Router
{
    public function requireAuth(): ?array
    {
        $user = (new AuthMiddleware())->user();

        if (!$user) {
            Response::json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
            return null;
        }

        return $user;
    }
}
