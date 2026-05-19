<?php
/**
 * Top of every page: html, head, navbar, sidebar wrapper.
 * Pages should include header.php, output their main content,
 * then include footer.php which closes the layout.
 */
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? APP_NAME;
$activeNav = $activeNav ?? '';
$user      = Auth::check() ? Auth::user() : null;
$theme     = $user['theme'] ?? ($_COOKIE['theme'] ?? 'light');
$userName  = $user['name'] ?? '';
$userInitial = $userName ? strtoupper(substr(trim($userName), 0, 1)) : '';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= e($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0d6efd">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>

<!-- Bootstrap 5 + Icons + Chart.js (CDN) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<!-- Google Fonts (Inter) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>">

<!-- PWA-ish manifest (optional) -->
<link rel="manifest" href="<?= url('assets/manifest.webmanifest') ?>">
</head>
<body>

<?php if (Auth::check()): ?>
<!-- Top navbar (mobile + desktop) -->
<nav class="navbar navbar-expand-lg fin-navbar sticky-top">
  <div class="container-fluid">
    <button class="btn btn-sm btn-outline-secondary me-2 d-lg-none" id="sidebarToggle" aria-label="Open menu">
      <i class="bi bi-list"></i>
    </button>
    <a class="navbar-brand fw-bold" href="<?= url('dashboard.php') ?>">
      <i class="bi bi-cash-coin text-primary"></i> <?= e(APP_NAME) ?>
    </a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <button class="btn" id="themeToggle" title="Toggle theme" aria-pressed="false">
        <i class="bi bi-moon-stars"></i>
      </button>
      <div class="dropdown">
        <button class="btn dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--ft-primary),var(--ft-primary-600));color:#fff;font-weight:700;">
            <?= e($userInitial) ?>
          </span>
          <span class="d-none d-sm-inline ms-1"><?= e($user['name']) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= url('modules/profile/index.php') ?>"><i class="bi bi-gear"></i> Profile</a></li>
          <li><a class="dropdown-item" href="<?= url('modules/backup/index.php') ?>"><i class="bi bi-cloud-arrow-down"></i> Backup</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<div class="fin-shell">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="fin-main">
    <div class="container-fluid p-3 p-md-4">
    <?php if ($msg = flash('success')): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?= e($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?= e($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
<?php else: ?>
<main class="fin-auth-shell">
<?php endif; ?>
