<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Config\Database;

class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function login(string $email, string $password): array|false
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        // Update last login
        $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

        // Store in session
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['branch_id']  = $user['branch_id'];

        // Regenerate session ID
        session_regenerate_id(true);

        return $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function user(): array
    {
        return [
            'id'        => $_SESSION['user_id']    ?? null,
            'name'      => $_SESSION['user_name']  ?? '',
            'email'     => $_SESSION['user_email'] ?? '',
            'role'      => $_SESSION['user_role']  ?? '',
            'branch_id' => $_SESSION['branch_id']  ?? null,
        ];
    }

    public static function isSuperAdmin(): bool
    {
        return ($_SESSION['user_role'] ?? '') === 'super_admin';
    }

    public static function isBranchAdmin(): bool
    {
        return ($_SESSION['user_role'] ?? '') === 'branch_admin';
    }

    public static function requireLogin(string $redirectTo = '/login.php'): void
    {
        if (!self::check()) {
            header('Location: ' . BASE_URL . $redirectTo);
            exit;
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        if (($_SESSION['user_role'] ?? '') !== $role) {
            http_response_code(403);
            exit('Access denied.');
        }
    }

    /** Branch admin can only access their own branch */
    public static function canAccessBranch(int $branchId): bool
    {
        if (self::isSuperAdmin()) {
            return true;
        }
        return (int) ($_SESSION['branch_id'] ?? 0) === $branchId;
    }
}
