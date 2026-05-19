<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Config/config.php';

use App\Helpers\Auth;

Auth::startSession();
Auth::logout();
header('Location: ' . BASE_URL . '/login.php');
exit;
