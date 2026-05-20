<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Config/config.php';

use App\Helpers\CustomerAuth;

CustomerAuth::startSession();

if (CustomerAuth::check()) {
    header('Location: ' . BASE_URL . '/customer/');
    exit;
}

$error = '';
$contact = trim((string)($_POST['contact'] ?? $_GET['contact'] ?? ''));
$orderNumber = trim((string)($_POST['order_number'] ?? $_GET['order_number'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer = CustomerAuth::login($contact, $orderNumber);
    if ($customer !== false) {
        header('Location: ' . BASE_URL . '/customer/');
        exit;
    }
    $error = 'Data tidak cocok. Gunakan email atau WhatsApp yang dipakai saat order, lalu isi nomor order dengan benar.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Portal Login - <?= htmlspecialchars(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <h1>Customer Portal</h1>
      <p>Masuk dengan kontak dan nomor order terakhir Anda</p>
    </div>

    <?php if ($error !== ''): ?>
      <div class="alert alert-error" style="margin-bottom:16px"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label" for="contact">Email atau WhatsApp</label>
        <input
          type="text"
          id="contact"
          name="contact"
          class="form-control"
          value="<?= htmlspecialchars($contact) ?>"
          placeholder="contoh@email.com atau 0812..."
          required
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="order_number">Nomor Order</label>
        <input
          type="text"
          id="order_number"
          name="order_number"
          class="form-control"
          value="<?= htmlspecialchars($orderNumber) ?>"
          placeholder="ORD-20260520-ABC123"
          required
        >
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%">Masuk ke Dashboard</button>
    </form>

    <p style="margin-top:14px;text-align:center;font-size:.82rem;color:var(--text-light)">
      Verifikasi ringan ini dipakai agar customer bisa cek loyalty point dan history order tanpa akun admin.
    </p>

    <p style="margin-top:10px;text-align:center">
      <a href="<?= BASE_URL ?>/" class="btn btn-outline">Kembali ke Beranda</a>
    </p>
  </div>
</div>
<script>
(() => {
  const contactInput = document.getElementById('contact');
  const orderInput = document.getElementById('order_number');
  if (!contactInput || !orderInput) return;

  if (contactInput.value.trim() !== '' && orderInput.value.trim() !== '') return;

  try {
    const raw = localStorage.getItem('customerPortalRecentLogin');
    if (!raw) return;
    const saved = JSON.parse(raw);
    if (typeof saved !== 'object' || saved === null) return;

    if (contactInput.value.trim() === '' && typeof saved.contact === 'string') {
      contactInput.value = saved.contact;
    }
    if (orderInput.value.trim() === '' && typeof saved.orderNumber === 'string') {
      orderInput.value = saved.orderNumber;
    }
  } catch (error) {
    console.warn('Failed to restore customer portal shortcut.', error);
  }
})();
</script>
</body>
</html>
