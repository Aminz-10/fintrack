<?php
require_once __DIR__ . '/includes/auth.php';

if (Auth::check()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$error = null;
$ok    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::csrfVerify();
    $name     = trim($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');
    $currency = trim($_POST['currency'] ?? 'RM') ?: 'RM';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) {
            $error = 'An account with that email already exists.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO users (name,email,password,currency) VALUES (?,?,?,?)'
            );
            $stmt->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                substr($currency, 0, 8),
            ]);
            $userId = (int)$pdo->lastInsertId();

            // Seed a few starter categories
            $cats = [
                ['Food','expense'], ['Transport','expense'], ['Bills','expense'],
                ['Salary','income'], ['Investment','income'],
            ];
            $catStmt = $pdo->prepare('INSERT INTO categories (user_id,name,type) VALUES (?,?,?)');
            foreach ($cats as $c) $catStmt->execute([$userId, $c[0], $c[1]]);

            Auth::login($userId, $name);
            header('Location: ' . url('dashboard.php'));
            exit;
        }
    }
}

$pageTitle = 'Create account';
include __DIR__ . '/includes/header.php';
?>
<div class="fin-auth-card card shadow-sm">
  <div class="card-body p-4 p-md-5">
    <div class="text-center mb-4">
      <div class="fin-brand-mark mb-2"><i class="bi bi-cash-coin"></i></div>
      <h3 class="fw-bold mb-1">Create your account</h3>
      <p class="text-muted small mb-0">Free, private, runs on your hosting.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger small"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <?= Auth::csrfField() ?>
      <div class="mb-3">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" required
               value="<?= e($_POST['name'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required
               value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="row g-2">
        <div class="col-sm-8 mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" minlength="6" required>
        </div>
        <div class="col-sm-4 mb-3">
          <label class="form-label">Currency</label>
          <input type="text" name="currency" class="form-control" maxlength="8"
                 value="<?= e($_POST['currency'] ?? 'RM') ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Confirm password</label>
        <input type="password" name="confirm" class="form-control" minlength="6" required>
      </div>
      <button class="btn btn-primary w-100" type="submit">
        <i class="bi bi-person-plus"></i> Create account
      </button>
    </form>

    <hr class="my-4">
    <p class="text-center small mb-0">
      Already a user?
      <a href="<?= url('login.php') ?>">Sign in</a>
    </p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
