<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Config/config.php';

use App\Helpers\Auth;

Auth::startSession();
Auth::requireLogin();

// Redirect to role-specific dashboard
if (Auth::isSuperAdmin()) {
    header('Location: ' . BASE_URL . '/dashboard/super/');
} else {
    header('Location: ' . BASE_URL . '/dashboard/branch/');
}
exit;
