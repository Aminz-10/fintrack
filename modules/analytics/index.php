<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::require();
$userId = Auth::id();
$cur    = currentCurrency();
$pdo    = Database::pdo();

$year = (int)($_GET['year'] ?? date('Y'));

// Most used (spent) category lifetime
$mostCat = $pdo->prepare(
    "SELECT category, SUM(amount) AS total, COUNT(*) AS cnt
     FROM transactions WHERE user_id=? AND type='expense'
     GROUP BY category ORDER BY total DESC LIMIT 5"
);
$mostCat->execute([$userId]);
$mostCat = $mostCat->fetchAll();

// Highest spending month (any year)
$highMonth = $pdo->prepare(
    "SELECT DATE_FORMAT(date,'%Y-%m') AS ym, SUM(amount) AS total
     FROM transactions WHERE user_id=? AND type='expense'
     GROUP BY ym ORDER BY total DESC LIMIT 1"
);
$highMonth->execute([$userId]);
$highMonth = $highMonth->fetch();

// Average daily spending (lifetime)
$avgDaily = $pdo->prepare(
    "SELECT COALESCE(SUM(amount),0)/GREATEST(DATEDIFF(MAX(date),MIN(date))+1,1) AS avg_daily
     FROM transactions WHERE user_id=? AND type='expense'"
);
$avgDaily->execute([$userId]);
$avgDaily = (float)$avgDaily->fetchColumn();

// Savings rate this year
$ytd = Tx::summary($userId, "$year-01-01", "$year-12-31");
$savingsRate = $ytd['income'] > 0
    ? max(0, ($ytd['income'] - $ytd['expense']) / $ytd['income']) * 100
    : 0;

$series = Tx::monthlySeries($userId, $year);

$pageTitle = 'Analytics';
$activeNav = 'analytics';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card mb-3">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h4 class="mb-0">Analytics</h4>
      <p class="text-muted small mb-0">Yearly insights and trends.</p>
    </div>
    <form class="d-flex gap-2" method="get">
      <input type="number" class="form-control form-control-sm" name="year" min="2000" max="2100" value="<?= (int)$year ?>" style="width:120px">
      <button class="btn btn-sm btn-primary"><i class="bi bi-arrow-clockwise"></i></button>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card h-100"><div class="card-body">
      <small class="text-muted">Top expense category</small>
      <h5 class="mb-0"><?= e($mostCat[0]['category'] ?? '—') ?></h5>
      <small class="text-muted"><?= isset($mostCat[0]) ? fmtMoney((float)$mostCat[0]['total']) : '—' ?></small>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card h-100"><div class="card-body">
      <small class="text-muted">Highest spending month</small>
      <h5 class="mb-0"><?= e($highMonth['ym'] ?? '—') ?></h5>
      <small class="text-muted"><?= isset($highMonth['ym']) ? fmtMoney((float)$highMonth['total']) : '—' ?></small>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card h-100"><div class="card-body">
      <small class="text-muted">Avg daily spending</small>
      <h5 class="mb-0"><?= fmtMoney($avgDaily) ?></h5>
      <small class="text-muted">all time</small>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card h-100"><div class="card-body">
      <small class="text-muted">Savings rate (<?= (int)$year ?>)</small>
      <h5 class="mb-0"><?= number_format($savingsRate, 1) ?>%</h5>
      <small class="text-muted"><?= fmtMoney($ytd['balance']) ?> saved</small>
    </div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card h-100"><div class="card-body">
      <h6 class="mb-3"><?= (int)$year ?> Income vs Expense</h6>
      <canvas id="annualChart" height="120"></canvas>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-body">
      <h6 class="mb-3">Top categories</h6>
      <?php if (empty($mostCat)): ?>
        <div class="text-center text-muted py-4 small">No data yet.</div>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($mostCat as $c): ?>
            <li class="list-group-item d-flex justify-content-between px-0">
              <span><?= e($c['category']) ?> <small class="text-muted">×<?= (int)$c['cnt'] ?></small></span>
              <strong><?= fmtMoney((float)$c['total']) ?></strong>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div></div>
  </div>
</div>

<script>
window.__analytics = { series: <?= json_encode($series) ?> };
</script>
<script src="<?= url('assets/js/analytics.js') ?>"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
