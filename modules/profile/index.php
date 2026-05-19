<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::require();
$userId = Auth::id();
$pdo = Database::pdo();
$user = Auth::user();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::csrfVerify();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name     = trim($_POST['name'] ?? '');
        $currency = trim($_POST['currency'] ?? 'RM') ?: 'RM';
        $theme    = ($_POST['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
        $language = preg_match('/^[a-z]{2}$/', $_POST['language'] ?? '') ? $_POST['language'] : 'en';

        if ($name === '') $errors[] = 'Name is required.';

        if (!$errors) {
            $pdo->prepare('UPDATE users SET name=?,currency=?,theme=?,language=? WHERE id=?')
                ->execute([$name, substr($currency, 0, 8), $theme, $language, $userId]);
            $_SESSION['user_currency'] = $currency;
            flash('success', 'Profile updated.');
            header('Location: ' . url('modules/profile/index.php'));
            exit;
        }
    } elseif ($action === 'password') {
        $current = (string)($_POST['current'] ?? '');
        $new     = (string)($_POST['new'] ?? '');
        $confirm = (string)($_POST['confirm'] ?? '');
        $row = $pdo->prepare('SELECT password FROM users WHERE id=?');
        $row->execute([$userId]);
        $hash = (string)$row->fetchColumn();

        if (!password_verify($current, $hash))    $errors[] = 'Current password is incorrect.';
        elseif (strlen($new) < 6)                 $errors[] = 'New password must be at least 6 characters.';
        elseif ($new !== $confirm)                $errors[] = 'New passwords do not match.';

        if (!$errors) {
            $pdo->prepare('UPDATE users SET password=? WHERE id=?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            flash('success', 'Password changed.');
            header('Location: ' . url('modules/profile/index.php'));
            exit;
        }
    }
}

$pageTitle = 'Profile';
$activeNav = 'profile';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card mb-3">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h4 class="mb-0">Profile settings</h4>
      <p class="text-muted small mb-0">Manage your account, preferences and password.</p>
    </div>
    <div class="d-none d-md-block small text-muted">Member since <?= e(date('M d, Y', strtotime($user['created_at']))) ?></div>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0">
    <?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
  </ul></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-body">
      <h6>Account</h6>
      <form method="post">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="profile">
        <div class="mb-2">
          <label class="form-label small">Name</label>
          <input class="form-control" name="name" required value="<?= e($user['name']) ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small">Email</label>
          <input class="form-control" value="<?= e($user['email']) ?>" disabled>
        </div>
        <div class="row">
          <div class="col-sm-6 mb-2">
            <label class="form-label small">Currency</label>
            <input class="form-control" name="currency" maxlength="8" value="<?= e($user['currency']) ?>">
          </div>
          <div class="col-sm-6 mb-2">
            <label class="form-label small">Language</label>
            <select class="form-select" name="language">
              <?php foreach (['en'=>'English','ms'=>'Bahasa Malaysia','zh'=>'中文','id'=>'Indonesia'] as $code=>$lbl): ?>
                <option value="<?= $code ?>" <?= $user['language']===$code?'selected':'' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small">Theme</label>
          <select class="form-select" name="theme">
            <option value="light" <?= $user['theme']==='light'?'selected':'' ?>>Light</option>
            <option value="dark"  <?= $user['theme']==='dark' ?'selected':'' ?>>Dark</option>
          </select>
        </div>
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100"><div class="card-body">
      <h6>Change password</h6>
      <form method="post">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="password">
        <div class="mb-2">
          <label class="form-label small">Current password</label>
          <input type="password" class="form-control" name="current" required>
        </div>
        <div class="mb-2">
          <label class="form-label small">New password</label>
          <input type="password" class="form-control" name="new" minlength="6" required>
        </div>
        <div class="mb-3">
          <label class="form-label small">Confirm new password</label>
          <input type="password" class="form-control" name="confirm" minlength="6" required>
        </div>
        <button class="btn btn-primary"><i class="bi bi-shield-check"></i> Update password</button>
      </form>
    </div></div>
  </div>
</div>

<div class="card mt-3"><div class="card-body small text-muted">
  Member since <?= e(date('M d, Y', strtotime($user['created_at']))) ?>
</div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
