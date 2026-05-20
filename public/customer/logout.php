<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Config/config.php';

use App\Helpers\CustomerAuth;

CustomerAuth::startSession();
CustomerAuth::logout();

header('Location: ' . BASE_URL . '/customer/login.php');
exit;
