<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

Auth::require();
$userId = Auth::id();
$cur = currentCurrency();

$today    = date('Y-m-d');
$weekFrom = date('Y-m-d', strtotime('monday this week'));
$weekTo   = date('Y-m-d', strtotime('sunday this week'));
$monthFrom = date('Y-m-01');
$monthTo   = date('Y-m-t');
$yearFrom  = date('Y-01-01');
$yearTo    = date('Y-12-31');

$all   = Tx::summary($userId);
$today_s   = Tx::summary($userId, $today, $today);
$week_s    = Tx::summary($userId, $weekFrom, $weekTo);
$month_s   = Tx::summary($userId, $monthFrom, $monthTo);
$year_s    = Tx::summary($userId, $yearFrom, $yearTo);
$recent    = Tx::recent($userId, 8);

// --- Range datasets used by the range switcher on the dashboard charts ---
$series30  = Tx::dailySeries($userId, date('Y-m-d', strtotime('-29 days')), $today);
$series7   = Tx::dailySeries($userId, date('Y-m-d', strtotime('-6 days')),  $today);
$series12m = Tx::monthlySeries($userId, (int)date('Y'));

$byCat       = Tx::byCategory($userId, $monthFrom, $monthTo);                 // month
$byCatWeek   = Tx::byCategory($userId, $weekFrom,  $weekTo);                  // week
$byCatYear   = Tx::byCategory($userId, $yearFrom,  $yearTo);                  // year

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/includes/header.php';
?>
<div class="card mb-4">
  <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-3">
<div class="fin-brand-mark" style="width:48px;height:48px;border-radius:12px;box-shadow:none;display:inline-flex;align-items:center;justify-content:center;">
  <i class="bi bi-wallet2"></i>
</div>
<div>
  <h4 class="mb-0">Welcome back, <?= e(explode(' ', Auth::user()['name'])[0]) ?> <span class="wave">👋</span></h4>
  <p class="text-muted small mb-0">Here's your money at a glance.</p>
</div>
    </div>
    <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
      <a href="<?= url('modules/transactions/edit.php') ?>" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i> New transaction
      </a>
      <a href="<?= url('modules/transactions/index.php') ?>" class="btn btn-outline-secondary d-none d-sm-inline">Transactions</a>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between"><span class="text-muted small">Balance</span>
          <span class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-wallet2"></i></span></div>
        <h4 class="fw-bold mt-2 mb-0"><?= fmtMoney($all['balance']) ?></h4>
        <span class="small text-muted">Income − Expenses</span>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between"><span class="text-muted small">Income (all time)</span>
          <span class="stat-icon bg-success-subtle text-success"><i class="bi bi-arrow-down-circle"></i></span></div>
        <h4 class="fw-bold mt-2 mb-0 text-success"><?= fmtMoney($all['income']) ?></h4>
        <span class="small text-muted">Total credited</span>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between"><span class="text-muted small">Expenses (all time)</span>
          <span class="stat-icon bg-danger-subtle text-danger"><i class="bi bi-arrow-up-circle"></i></span></div>
        <h4 class="fw-bold mt-2 mb-0 text-danger"><?= fmtMoney($all['expense']) ?></h4>
        <span class="small text-muted">Total spent</span>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between"><span class="text-muted small">Today</span>
          <span class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-sun"></i></span></div>
        <h4 class="fw-bold mt-2 mb-0"><?= fmtMoney($today_s['expense']) ?></h4>
        <span class="small text-muted">+<?= fmtMoney($today_s['income']) ?> in</span>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card h-100"><div class="card-body">
      <h6 class="text-muted">This week</h6>
      <div class="d-flex justify-content-between"><span>Spent</span><strong class="text-danger"><?= fmtMoney($week_s['expense']) ?></strong></div>
      <div class="d-flex justify-content-between"><span>Earned</span><strong class="text-success"><?= fmtMoney($week_s['income']) ?></strong></div>
      <hr class="my-2">
      <div class="d-flex justify-content-between"><span>Net</span><strong><?= fmtMoney($week_s['balance']) ?></strong></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card h-100"><div class="card-body">
      <h6 class="text-muted">This month</h6>
      <div class="d-flex justify-content-between"><span>Spent</span><strong class="text-danger"><?= fmtMoney($month_s['expense']) ?></strong></div>
      <div class="d-flex justify-content-between"><span>Earned</span><strong class="text-success"><?= fmtMoney($month_s['income']) ?></strong></div>
      <hr class="my-2">
      <div class="d-flex justify-content-between"><span>Net</span><strong><?= fmtMoney($month_s['balance']) ?></strong></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card h-100"><div class="card-body">
      <h6 class="text-muted">This year</h6>
      <div class="d-flex justify-content-between"><span>Spent</span><strong class="text-danger"><?= fmtMoney($year_s['expense']) ?></strong></div>
      <div class="d-flex justify-content-between"><span>Earned</span><strong class="text-success"><?= fmtMoney($year_s['income']) ?></strong></div>
      <hr class="my-2">
      <div class="d-flex justify-content-between"><span>Net</span><strong><?= fmtMoney($year_s['balance']) ?></strong></div>
    </div></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card h-100"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h6 class="mb-0" id="spendTitle">Spending — last 30 days</h6>
          <span class="small text-muted">Income vs Expense</span>
        </div>
        <div class="btn-group btn-group-sm" role="group" aria-label="Spending range">
            <button type="button" data-range="week" aria-pressed="false" class="btn btn-outline-secondary">Week</button>
            <button type="button" data-range="month" aria-pressed="true" class="btn btn-primary active">Month</button>
            <button type="button" data-range="year" aria-pressed="false" class="btn btn-outline-secondary">Year</button>
          </div>
      </div>
      <canvas id="spendChart" height="120"></canvas>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h6 class="mb-0" id="catTitle">By category — this month</h6>
        <div class="btn-group btn-group-sm" role="group" aria-label="Category range">
          <button type="button" data-cat-range="week" aria-pressed="false" class="btn btn-outline-secondary">Week</button>
          <button type="button" data-cat-range="month" aria-pressed="true" class="btn btn-primary active">Month</button>
          <button type="button" data-cat-range="year" aria-pressed="false" class="btn btn-outline-secondary">Year</button>
        </div>
      </div>
      <canvas id="catChart" height="180"></canvas>
      <div id="catEmpty" class="text-center text-muted py-4 small d-none">
        <i class="bi bi-pie-chart" style="font-size:1.4rem;opacity:.4;"></i>
        <div class="mt-1">No expenses in this range.</div>
      </div>
    </div></div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0">Recent transactions</h6>
      <a href="<?= url('modules/transactions/index.php') ?>" class="small bubble text-decoration-none">View all <i class="bi bi-arrow-right"></i></a>
    </div>
    <?php if (empty($recent)): ?>
      <div class="text-center text-muted py-5 small">
        Nothing recorded yet. <a href="<?= url('modules/transactions/edit.php') ?>">Add your first transaction</a>.
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 table-borderless">
        <thead><tr>
          <th>Date</th><th>Title</th><th>Category</th>
          <th class="d-none d-md-table-cell">Method</th>
          <th class="text-end">Amount</th>
        </tr></thead>
        <tbody>
        <?php foreach ($recent as $t): ?>
          <tr>
            <td class="text-nowrap small text-muted"><?= e(date('M d', strtotime($t['date']))) ?></td>
            <td><a class="text-decoration-none" href="<?= url('modules/transactions/edit.php?id=' . $t['id']) ?>"><?= e($t['title']) ?></a></td>
            <td><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($t['category']) ?></span></td>
            <td class="d-none d-md-table-cell small text-muted"><?= e($t['payment_method']) ?></td>
            <td class="text-end fw-semibold <?= $t['type'] === 'income' ? 'text-success' : 'text-danger' ?>">
              <?= $t['type'] === 'income' ? '+' : '−' ?><?= fmtMoney((float)$t['amount']) ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
window.__dashboard = {
  series: <?= json_encode($series30, JSON_UNESCAPED_UNICODE) ?>,
  byCat:  <?= json_encode($byCat, JSON_UNESCAPED_UNICODE) ?>,
  currency: <?= json_encode($cur) ?>,
  ranges: {
    series: {
      week:  <?= json_encode($series7,   JSON_UNESCAPED_UNICODE) ?>,
      month: <?= json_encode($series30,  JSON_UNESCAPED_UNICODE) ?>,
      year:  <?= json_encode($series12m, JSON_UNESCAPED_UNICODE) ?>
    },
    byCat: {
      week:  <?= json_encode($byCatWeek, JSON_UNESCAPED_UNICODE) ?>,
      month: <?= json_encode($byCat,     JSON_UNESCAPED_UNICODE) ?>,
      year:  <?= json_encode($byCatYear, JSON_UNESCAPED_UNICODE) ?>
    },
    labels: {
      week:  'this week',
      month: 'this month',
      year:  'this year'
    }
  }
};
</script>
<script src="<?= url('assets/js/dashboard.js') ?>?v=<?= e(date('YmdHi')) ?>"></script>
<script>
// Visible fallback: if neither chart has rendered after 3 s, show a hint
setTimeout(function () {
  function ghost(canvas, msg) {
    if (!canvas) return;
    if (canvas.dataset.rendered === '1') return;
    var p = document.createElement('div');
    p.className = 'text-center text-muted small py-4';
    p.innerHTML = '<i class="bi bi-info-circle"></i> ' + msg;
    canvas.replaceWith(p);
  }
  if (typeof Chart === 'undefined') {
    ghost(document.getElementById('spendChart'),
      "Chart library didn't load. Check your internet connection (Chart.js is loaded from a CDN).");
    ghost(document.getElementById('catChart'),
      "Chart library didn't load.");
  }
}, 3000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
