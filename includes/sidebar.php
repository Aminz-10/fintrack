<?php
/**
 * Off-canvas / fixed sidebar navigation.
 * Set $activeNav = 'transactions' (etc.) on the page to highlight.
 */
$nav = [
    'dashboard'    => ['Dashboard',    'speedometer2', url('dashboard.php')],
    'transactions' => ['Transactions', 'list-ul',     url('modules/transactions/index.php')],
    'reports'      => ['Reports',      'graph-up',    url('modules/reports/index.php')],
    'analytics'    => ['Analytics',    'pie-chart',   url('modules/analytics/index.php')],
    'budget'       => ['Budgets',      'wallet2',     url('modules/budget/index.php')],
    'export'       => ['Export',       'box-arrow-up', url('modules/export/index.php')],
    'import'       => ['Import',       'box-arrow-in-down', url('modules/import/index.php')],
    'backup'       => ['Backup',       'cloud-arrow-down', url('modules/backup/index.php')],
    'profile'      => ['Profile',      'person-gear', url('modules/profile/index.php')],
];
?>
<aside class="fin-sidebar" id="finSidebar">
  <div class="fin-sidebar-inner">
    <div class="sidebar-brand px-3 pb-2">
      <a href="<?= url('dashboard.php') ?>" class="d-flex align-items-center gap-2 text-decoration-none">
        <span class="fin-brand-mark"><i class="bi bi-cash-coin"></i></span>
        <div>
          <div class="fw-bold"><?= e(APP_NAME) ?></div>
          <div class="small text-muted">v<?= e(APP_VERSION) ?></div>
        </div>
      </a>
    </div>
    <ul class="nav flex-column">
      <?php foreach ($nav as $key => [$label, $icon, $href]): ?>
        <li class="nav-item">
          <a class="nav-link <?= $activeNav === $key ? 'active' : '' ?>" href="<?= $href ?>">
            <i class="bi bi-<?= $icon ?>"></i>
            <span><?= e($label) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="px-3 mt-auto small text-muted">
      <hr>
      <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?>
    </div>
  </div>
</aside>
<div class="fin-sidebar-backdrop" id="finSidebarBackdrop"></div>
