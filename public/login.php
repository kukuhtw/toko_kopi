<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Config/config.php';

use App\Helpers\Auth;
use App\Helpers\Csrf;

Auth::startSession();

// Already logged in
if (Auth::check()) {
    header('Location: ' . BASE_URL . '/dashboard/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $user = Auth::login($email, $password);
        if ($user) {
            header('Location: ' . BASE_URL . '/dashboard/');
            exit;
        } else {
            $error = 'Email atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Toko Kopi</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <h1>☕ Toko Kopi</h1>
      <p>Chatbot Order Management System</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= Csrf::field() ?>
      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="admin@tokokopi.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;">
        Masuk
      </button>
    </form>

    <p style="text-align:center;margin-top:24px;font-size:.8rem;color:var(--text-light);">
      Demo: admin@tokokopi.com / password
    </p>

    <p style="text-align:center;margin-top:12px;font-size:.82rem">
      <a href="<?= BASE_URL ?>/" style="color:var(--primary);text-decoration:none">← Kembali ke Beranda</a>
    </p>
  </div>
</div>
</body>
</html>
