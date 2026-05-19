<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::require();
$userId = Auth::id();

// Build filter set from query string
$filter = [
    'q'              => trim($_GET['q'] ?? ''),
    'type'           => $_GET['type'] ?? '',
    'category'       => $_GET['category'] ?? '',
    'payment_method' => $_GET['payment_method'] ?? '',
    'date_from'      => $_GET['date_from'] ?? '',
    'date_to'        => $_GET['date_to'] ?? '',
    'min_amount'     => $_GET['min_amount'] ?? '',
    'max_amount'     => $_GET['max_amount'] ?? '',
];

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;
$total   = Tx::count($userId, $filter);
$rows    = Tx::list($userId, $filter, $perPage, $offset);
$pages   = max(1, (int)ceil($total / $perPage));

$pageTitle = 'Transactions';
$activeNav = 'transactions';
include __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h4 class="mb-0 fw-bold">Transactions</h4>
    <small class="text-muted"><?= number_format($total) ?> total · <?= number_format(count($rows)) ?> on this page</small>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteRangeModal">
      <i class="bi bi-calendar-x"></i> Delete by date
    </button>
    <a href="<?= url('modules/import/index.php') ?>" class="btn btn-outline-secondary">
      <i class="bi bi-box-arrow-in-down"></i> Import
    </a>
    <a href="<?= url('modules/transactions/edit.php') ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg"></i> New
    </a>
  </div>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2" method="get">
      <div class="col-md-3">
        <label class="form-label small">Search</label>
        <input class="form-control" name="q" placeholder="Title or note" value="<?= e($filter['q']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small">Type</label>
        <select class="form-select" name="type">
          <option value="">All</option>
          <option value="income"  <?= $filter['type'] === 'income'  ? 'selected' : '' ?>>Income</option>
          <option value="expense" <?= $filter['type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Category</label>
        <select class="form-select" name="category">
          <option value="">All</option>
          <?php foreach (Tx::CATEGORIES as $c): ?>
            <option value="<?= e($c) ?>" <?= $filter['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Method</label>
        <select class="form-select" name="payment_method">
          <option value="">All</option>
          <?php foreach (Tx::PAYMENT_METHODS as $m): ?>
            <option value="<?= e($m) ?>" <?= $filter['payment_method'] === $m ? 'selected' : '' ?>><?= e($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small">Date</label>
        <div class="input-group">
          <input type="date" class="form-control" name="date_from" value="<?= e($filter['date_from']) ?>">
          <span class="input-group-text">→</span>
          <input type="date" class="form-control" name="date_to" value="<?= e($filter['date_to']) ?>">
        </div>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Min amount</label>
        <input type="number" step="0.01" class="form-control" name="min_amount" value="<?= e($filter['min_amount']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small">Max amount</label>
        <input type="number" step="0.01" class="form-control" name="max_amount" value="<?= e($filter['max_amount']) ?>">
      </div>
      <div class="col-md-8 d-flex align-items-end gap-2 flex-wrap">
        <button class="btn btn-primary"><i class="bi bi-funnel"></i> Apply</button>
        <a class="btn btn-outline-secondary" href="<?= url('modules/transactions/index.php') ?>">Reset</a>
        <a class="btn btn-outline-secondary ms-auto" href="<?= url('modules/export/index.php?' . http_build_query($filter)) ?>">
          <i class="bi bi-box-arrow-up"></i> Export filtered
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Sticky bulk action bar (appears when rows are selected) -->
<form id="bulkForm" method="post" action="<?= url('modules/transactions/delete.php') ?>"
      onsubmit="return confirmBulk(this)">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="mode" value="bulk">

  <div id="bulkBar" class="card mb-3 d-none" style="position: sticky; top: 70px; z-index: 5;">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
      <div class="d-flex align-items-center gap-3">
        <span class="badge bg-primary rounded-pill" style="font-size:.85rem; padding:.45em .8em;">
          <i class="bi bi-check2-square"></i>
          <span id="bulkCount">0</span> selected
        </span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkClear">
          <i class="bi bi-x-lg"></i> Clear
        </button>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-sm btn-danger">
          <i class="bi bi-trash"></i> Delete selected
        </button>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <?php if (empty($rows)): ?>
        <div class="p-5 text-center text-muted">
          <i class="bi bi-inbox" style="font-size:2rem; opacity:.5;"></i>
          <p class="mt-2 mb-0">No transactions match your filters.</p>
          <a class="btn btn-sm btn-outline-secondary mt-3" href="<?= url('modules/transactions/index.php') ?>">Clear filters</a>
        </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-borderless">
          <thead>
            <tr>
              <th style="width:42px;">
                <input class="form-check-input" type="checkbox" id="selectAllPage" title="Select all on this page">
              </th>
              <th>Date</th>
              <th>Title</th>
              <th>Category</th>
              <th class="d-none d-md-table-cell">Method</th>
              <th class="text-end">Amount</th>
              <th class="text-end" style="width:120px;"></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $t): ?>
            <tr data-row-id="<?= (int)$t['id'] ?>">
              <td>
                <input class="form-check-input row-check" type="checkbox" name="ids[]"
                       value="<?= (int)$t['id'] ?>" aria-label="Select transaction">
              </td>
              <td class="small text-muted text-nowrap"><?= e(date('M d, Y', strtotime($t['date']))) ?></td>
              <td>
                <a class="text-decoration-none fw-semibold" href="<?= url('modules/transactions/edit.php?id=' . $t['id']) ?>">
                  <?= e($t['title']) ?>
                </a>
                <?php if (!empty($t['note'])): ?>
                  <div class="small text-muted text-truncate" style="max-width:280px"><?= e($t['note']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($t['category']) ?></span></td>
              <td class="d-none d-md-table-cell small text-muted"><?= e($t['payment_method']) ?></td>
              <td class="text-end fw-semibold <?= $t['type'] === 'income' ? 'text-success' : 'text-danger' ?>">
                <?= $t['type'] === 'income' ? '+' : '−' ?><?= fmtMoney((float)$t['amount']) ?>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-secondary" href="<?= url('modules/transactions/edit.php?id=' . $t['id']) ?>" title="Edit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <button type="button" class="btn btn-outline-danger js-single-delete"
                          data-id="<?= (int)$t['id'] ?>" data-title="<?= e($t['title']) ?>"
                          title="Delete">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span class="small text-muted">Showing <?= count($rows) ?> of <?= number_format($total) ?> records</span>
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php
            $params = $filter;
            for ($i = 1; $i <= $pages; $i++):
              if ($pages > 8 && $i > 2 && $i < $pages - 1 && abs($i - $page) > 1) {
                if ($i === 3) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                continue;
              }
              $params['page'] = $i;
          ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
              <a class="page-link" href="?<?= http_build_query($params) ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
    <?php endif; ?>
  </div>
</form>

<!-- Hidden single-row delete form (used by per-row delete button) -->
<form id="singleDeleteForm" method="post" action="<?= url('modules/transactions/delete.php') ?>" class="d-none">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="mode" value="single">
  <input type="hidden" name="id" id="singleDeleteId" value="">
</form>

<!-- Delete by date / range modal -->
<div class="modal fade" id="deleteRangeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?= url('modules/transactions/delete.php') ?>" id="rangeForm"
            onsubmit="return confirmRange(this)">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="mode" value="range">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-calendar-x text-danger"></i> Delete by date</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning small mb-3">
            <i class="bi bi-exclamation-triangle"></i>
            This permanently removes every transaction in the chosen date(s). It cannot be undone — use Backup first if you're unsure.
          </div>

          <ul class="nav nav-pills mb-3" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tabRange" type="button" role="tab">Date range</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabSingle" type="button" role="tab">Single date</button>
            </li>
          </ul>

          <div class="tab-content">
            <div class="tab-pane fade show active" id="tabRange" role="tabpanel">
              <div class="row g-2">
                <div class="col-sm-6">
                  <label class="form-label small">From</label>
                  <input type="date" class="form-control" id="rangeFrom" name="date_from"
                         value="<?= e($filter['date_from'] ?: date('Y-m-01')) ?>">
                </div>
                <div class="col-sm-6">
                  <label class="form-label small">To</label>
                  <input type="date" class="form-control" id="rangeTo" name="date_to"
                         value="<?= e($filter['date_to'] ?: date('Y-m-d')) ?>">
                </div>
              </div>
              <div class="mt-2 d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-outline-secondary js-preset" data-preset="today">Today</button>
                <button type="button" class="btn btn-sm btn-outline-secondary js-preset" data-preset="yesterday">Yesterday</button>
                <button type="button" class="btn btn-sm btn-outline-secondary js-preset" data-preset="thisWeek">This week</button>
                <button type="button" class="btn btn-sm btn-outline-secondary js-preset" data-preset="thisMonth">This month</button>
                <button type="button" class="btn btn-sm btn-outline-secondary js-preset" data-preset="lastMonth">Last month</button>
                <button type="button" class="btn btn-sm btn-outline-secondary js-preset" data-preset="thisYear">This year</button>
              </div>
            </div>
            <div class="tab-pane fade" id="tabSingle" role="tabpanel">
              <label class="form-label small">Date</label>
              <input type="date" class="form-control" id="singleDate" value="<?= e(date('Y-m-d')) ?>">
              <div class="form-text">Deletes every transaction recorded on this exact day.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  const bulkBar     = document.getElementById('bulkBar');
  const bulkCount   = document.getElementById('bulkCount');
  const bulkClear   = document.getElementById('bulkClear');
  const selectAll   = document.getElementById('selectAllPage');
  const rowChecks   = Array.from(document.querySelectorAll('.row-check'));

  function refreshBar() {
    const checked = rowChecks.filter(c => c.checked);
    bulkCount.textContent = checked.length;
    bulkBar.classList.toggle('d-none', checked.length === 0);
    if (selectAll) {
      const total = rowChecks.length;
      selectAll.checked       = total > 0 && checked.length === total;
      selectAll.indeterminate = checked.length > 0 && checked.length < total;
    }
  }

  rowChecks.forEach(c => c.addEventListener('change', refreshBar));
  if (selectAll) selectAll.addEventListener('change', () => {
    rowChecks.forEach(c => { c.checked = selectAll.checked; });
    refreshBar();
  });
  if (bulkClear) bulkClear.addEventListener('click', () => {
    rowChecks.forEach(c => { c.checked = false; });
    refreshBar();
  });

  // Per-row delete: route through the hidden single-delete form so we keep CSRF.
  document.querySelectorAll('.js-single-delete').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-id');
      const title = btn.getAttribute('data-title') || 'this transaction';
      if (!confirm('Delete "' + title + '"? This cannot be undone.')) return;
      document.getElementById('singleDeleteId').value = id;
      document.getElementById('singleDeleteForm').submit();
    });
  });

  window.confirmBulk = function (form) {
    const n = bulkCount.textContent;
    return confirm('Delete ' + n + ' selected transaction' + (n === '1' ? '' : 's') + '? This cannot be undone.');
  };

  // ----- Range / single-date modal -----
  const rangeFrom  = document.getElementById('rangeFrom');
  const rangeTo    = document.getElementById('rangeTo');
  const singleDate = document.getElementById('singleDate');

  document.querySelectorAll('.js-preset').forEach(btn => {
    btn.addEventListener('click', () => {
      const now = new Date();
      const ymd = (d) => d.toISOString().slice(0, 10);
      let from, to;
      switch (btn.getAttribute('data-preset')) {
        case 'today':
          from = to = ymd(now); break;
        case 'yesterday': {
          const d = new Date(now); d.setDate(d.getDate() - 1);
          from = to = ymd(d); break;
        }
        case 'thisWeek': {
          const day = (now.getDay() + 6) % 7; // 0 = Monday
          const mon = new Date(now); mon.setDate(now.getDate() - day);
          const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
          from = ymd(mon); to = ymd(sun); break;
        }
        case 'thisMonth':
          from = ymd(new Date(now.getFullYear(), now.getMonth(), 1));
          to   = ymd(new Date(now.getFullYear(), now.getMonth() + 1, 0));
          break;
        case 'lastMonth':
          from = ymd(new Date(now.getFullYear(), now.getMonth() - 1, 1));
          to   = ymd(new Date(now.getFullYear(), now.getMonth(), 0));
          break;
        case 'thisYear':
          from = now.getFullYear() + '-01-01';
          to   = now.getFullYear() + '-12-31';
          break;
      }
      if (from && to) { rangeFrom.value = from; rangeTo.value = to; }
    });
  });

  window.confirmRange = function (form) {
    // If the Single date tab is active, copy that into the hidden fields.
    const singlePane = document.getElementById('tabSingle');
    if (singlePane && singlePane.classList.contains('active') && singlePane.classList.contains('show')) {
      if (!singleDate.value) {
        alert('Please choose a date.');
        return false;
      }
      rangeFrom.value = singleDate.value;
      rangeTo.value   = singleDate.value;
    }
    if (!rangeFrom.value && !rangeTo.value) {
      alert('Please choose a date range.');
      return false;
    }
    const f = rangeFrom.value || rangeTo.value;
    const t = rangeTo.value   || rangeFrom.value;
    const label = (f === t) ? ('on ' + f) : ('between ' + f + ' and ' + t);
    return confirm('Permanently delete EVERY transaction ' + label + '? This cannot be undone.');
  };
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
