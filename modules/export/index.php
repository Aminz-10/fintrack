<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::require();
$userId = Auth::id();

$filter = [
    'type'           => $_GET['type'] ?? '',
    'category'       => $_GET['category'] ?? '',
    'payment_method' => $_GET['payment_method'] ?? '',
    'date_from'      => $_GET['date_from'] ?? date('Y-m-01'),
    'date_to'        => $_GET['date_to']   ?? date('Y-m-t'),
    'q'              => $_GET['q'] ?? '',
];
$count = Tx::count($userId, $filter);

$pageTitle = 'Export';
$activeNav = 'export';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card mb-3">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h4 class="mb-0">Export</h4>
      <p class="text-muted small mb-0">Export your transactions in common formats. Narrow the range with filters.</p>
    </div>
    <div class="d-none d-md-block small text-muted"><?= number_format($count) ?> results</div>
  </div>
</div>

<div class="card mb-3"><div class="card-body">
  <form class="row g-2" method="get">
    <div class="col-md-3">
      <label class="form-label small">From</label>
      <input type="date" class="form-control" name="date_from" value="<?= e($filter['date_from']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small">To</label>
      <input type="date" class="form-control" name="date_to" value="<?= e($filter['date_to']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label small">Type</label>
      <select name="type" class="form-select">
        <option value="">All</option>
        <option value="income"  <?= $filter['type']==='income'?'selected':'' ?>>Income</option>
        <option value="expense" <?= $filter['type']==='expense'?'selected':'' ?>>Expense</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small">Category</label>
      <select name="category" class="form-select">
        <option value="">All</option>
        <?php foreach (Tx::CATEGORIES as $c): ?>
          <option value="<?= e($c) ?>" <?= $filter['category']===$c?'selected':'' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Apply</button>
    </div>
  </form>
</div></div>

<div class="alert alert-info small">
  <i class="bi bi-info-circle"></i>
  <strong><?= number_format($count) ?></strong> transactions match these filters.
</div>

<div class="row g-3">
  <?php
  $formats = [
    ['csv','CSV','filetype-csv','Spreadsheet-friendly'],
    ['xlsx','Excel (XML)','filetype-xls','Opens in Excel / Numbers'],
    ['pdf','PDF (HTML)','filetype-pdf','Print as PDF from browser'],
    ['txt','Plain Text','filetype-txt','Apple Notes friendly'],
    ['md','Markdown','filetype-md','Apple Notes / Bear / Obsidian'],
  ];
  $qs = http_build_query($filter);
  foreach ($formats as [$ext,$label,$icon,$desc]):
  ?>
  <div class="col-md-6 col-lg-4">
    <a class="card text-decoration-none h-100" href="<?= url('modules/export/run.php?format=' . $ext . '&' . $qs) ?>">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="display-6 text-primary"><i class="bi bi-<?= $icon ?>"></i></span>
        <div>
          <h6 class="mb-0"><?= e($label) ?></h6>
          <small class="text-muted"><?= e($desc) ?></small>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
