<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::require();
$userId = Auth::id();

// View: daily | weekly | monthly | yearly
$view = $_GET['view'] ?? 'monthly';
if (!in_array($view, ['daily','weekly','monthly','yearly'], true)) $view = 'monthly';

$today = new DateTimeImmutable('today');
$year = (int)($_GET['year'] ?? $today->format('Y'));

[$from, $to, $title, $series] = match ($view) {
    'daily' => (function () use ($userId, $today) {
        $from = $today->format('Y-m-d');
        $to   = $from;
        return [$from, $to, 'Daily — ' . $today->format('M d, Y'),
                Tx::dailySeries($userId, $today->modify('-13 days')->format('Y-m-d'), $from)];
    })(),
    'weekly' => (function () use ($userId, $today) {
        $from = $today->modify('monday this week')->format('Y-m-d');
        $to   = $today->modify('sunday this week')->format('Y-m-d');
        return [$from, $to, 'Weekly — week of ' . $from,
                Tx::dailySeries($userId, $from, $to)];
    })(),
    'monthly' => (function () use ($userId, $today) {
        $from = $today->format('Y-m-01');
        $to   = $today->format('Y-m-t');
        return [$from, $to, 'Monthly — ' . $today->format('F Y'),
                Tx::dailySeries($userId, $from, $to)];
    })(),
    'yearly' => (function () use ($userId, $year) {
        $from = "$year-01-01";
        $to   = "$year-12-31";
        return [$from, $to, "Yearly — $year",
                Tx::monthlySeries($userId, $year)];
    })(),
};

$summary = Tx::summary($userId, $from, $to);
$byCat   = Tx::byCategory($userId, $from, $to);
$rows    = Tx::list($userId, ['date_from' => $from, 'date_to' => $to], 100);

$pageTitle = 'Reports';
$activeNav = 'reports';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card mb-3">
  <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
    <div>
      <h4 class="mb-0">Reports</h4>
      <p class="text-muted small mb-0">Generate time-based reports and exports.</p>
    </div>
    <div class="btn-group" role="group">
      <?php foreach (['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly','yearly'=>'Yearly'] as $v=>$lbl): ?>
        <a class="btn btn-sm <?= $view===$v ? 'btn-primary' : 'btn-outline-primary' ?>"
           href="?view=<?= $v ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card mb-3"><div class="card-body">
  <h5 class="mb-3"><?= e($title) ?></h5>
  <div class="row g-3">
    <div class="col-md-4">
      <div class="p-3 rounded bg-success-subtle">
        <small class="text-success-emphasis">Income</small>
        <h4 class="mb-0 text-success-emphasis"><?= fmtMoney($summary['income']) ?></h4>
      </div>
    </div>
    <div class="col-md-4">
      <div class="p-3 rounded bg-danger-subtle">
        <small class="text-danger-emphasis">Expense</small>
        <h4 class="mb-0 text-danger-emphasis"><?= fmtMoney($summary['expense']) ?></h4>
      </div>
    </div>
    <div class="col-md-4">
      <div class="p-3 rounded bg-primary-subtle">
        <small class="text-primary-emphasis">Net</small>
        <h4 class="mb-0 text-primary-emphasis"><?= fmtMoney($summary['balance']) ?></h4>
      </div>
    </div>
  </div>
</div></div>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card h-100"><div class="card-body">
      <h6 class="mb-3">Trend</h6>
      <canvas id="reportChart" height="120"></canvas>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-body">
      <h6 class="mb-3">By category</h6>
      <?php if (empty($byCat)): ?>
        <div class="text-center text-muted py-4 small">No expenses in this range.</div>
      <?php else: ?>
        <canvas id="reportCatChart" height="180"></canvas>
        <ul class="list-unstyled small mt-3 mb-0">
        <?php foreach (array_slice($byCat, 0, 5) as $c): ?>
          <li class="d-flex justify-content-between"><span><?= e($c['category']) ?></span>
            <strong><?= fmtMoney((float)$c['total']) ?></strong></li>
        <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div></div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="mb-0">Transactions in this range</h6>
      <a class="btn btn-sm btn-outline-secondary"
         href="<?= url('modules/export/index.php?date_from=' . $from . '&date_to=' . $to) ?>">
         <i class="bi bi-box-arrow-up"></i> Export this report
      </a>
    </div>
    <?php if (empty($rows)): ?>
      <div class="p-4 text-center text-muted small">No transactions.</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0 table-borderless">
        <thead><tr><th>Date</th><th>Title</th><th>Category</th><th>Method</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $t): ?>
          <tr>
            <td class="small text-muted text-nowrap"><?= e($t['date']) ?></td>
            <td><?= e($t['title']) ?></td>
            <td><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($t['category']) ?></span></td>
            <td class="small text-muted"><?= e($t['payment_method']) ?></td>
            <td class="text-end fw-semibold <?= $t['type']==='income'?'text-success':'text-danger' ?>">
              <?= $t['type']==='income'?'+':'−' ?><?= fmtMoney((float)$t['amount']) ?>
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
window.__report = {
  view: <?= json_encode($view) ?>,
  series: <?= json_encode($series, JSON_UNESCAPED_UNICODE) ?>,
  byCat: <?= json_encode($byCat, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= url('assets/js/reports.js') ?>"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
