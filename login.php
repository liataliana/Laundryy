<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        // First try admin
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            setFlash('success', 'Selamat datang, ' . $admin['name'] . '!');
            redirect(APP_URL . '/admin/dashboard.php');
            exit;
        }

        // Then try user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            setFlash('success', 'Selamat datang, ' . $user['name'] . '!');
            redirect(APP_URL . '/user/dashboard.php');
            exit;
        }

        $error = 'Email atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-icon">🧺</div>
    </div>
    <h1 class="auth-title"><?= APP_NAME ?></h1>
    <p class="auth-subtitle">Masuk ke sistem laundry</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="nama@email.com" value="<?= sanitize($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password">
      </div>
      <button type="submit" class="btn btn-primary">Masuk</button>
    </form>

    <p class="bottom-text">Belum punya akun Pelanggan? <a href="<?= APP_URL ?>/user/register.php">Daftar sekarang</a></p>
  </div>
</div>
</body>
</html>
