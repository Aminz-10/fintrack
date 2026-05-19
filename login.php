<?php
require_once __DIR__ . '/includes/auth.php';

if (Auth::check()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::csrfVerify();
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } elseif (Auth::attempt($email, $password, $remember)) {
        header('Location: ' . url('dashboard.php'));
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}

$pageTitle = 'Sign in';
include __DIR__ . '/includes/header.php';
?>
<div class="fin-auth-card card shadow-sm">
  <div class="card-body p-4 p-md-5">
    <div class="text-center mb-4">
      <div class="fin-brand-mark mb-2"><i class="bi bi-cash-coin"></i></div>
      <h3 class="fw-bold mb-1"><?= e(APP_NAME) ?></h3>
      <p class="text-muted small mb-0">Track every ringgit. Save with intent.</p>
    </div>

    <?php if (!empty($_GET['timeout'])): ?>
      <div class="alert alert-warning small">Session expired. Please sign in again.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger small"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="on" novalidate>
      <?= Auth::csrfField() ?>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required
               value="<?= e($_POST['email'] ?? '') ?>" autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <div class="form-check mb-3">
        <input type="checkbox" name="remember" id="remember" class="form-check-input">
        <label class="form-check-label" for="remember">Remember me</label>
      </div>
      <button class="btn btn-primary w-100" type="submit">
        <i class="bi bi-box-arrow-in-right"></i> Sign in
      </button>
    </form>

    <hr class="my-4">
    <p class="text-center small mb-0">
      Don't have an account?
      <a href="<?= url('register.php') ?>">Create one</a>
    </p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
