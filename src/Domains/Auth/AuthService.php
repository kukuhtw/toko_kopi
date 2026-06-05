<?php

declare(strict_types=1);

namespace KopiBot\Domains\Auth;

class AuthService
{
    public function __construct(
        private UserRepository $users = new UserRepository(),
        private PasswordHasher $hasher = new PasswordHasher(),
        private JwtService $jwt = new JwtService()
    ) {}

    public function register(UserDTO $dto): array
    {
        if ($this->users->findByEmail($dto->tenantId, $dto->email)) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        $userId = $this->users->create($dto, $this->hasher->hash($dto->password));

        return [
            'success' => true,
            'user_id' => $userId,
        ];
    }

    public function login(int $tenantId, string $email, string $password): array
    {
        $user = $this->users->findByEmail($tenantId, $email);

        if (!$user || !$this->hasher->verify($password, (string) $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        if ((int) $user['is_active'] !== 1) {
            return ['success' => false, 'message' => 'User is inactive'];
        }

        $token = $this->jwt->issue([
            'tenant_id' => (int) $user['tenant_id'],
            'user_id' => (int) $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
        ]);

        return [
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => (int) $user['id'],
                'tenant_id' => (int) $user['tenant_id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ];
    }
}
