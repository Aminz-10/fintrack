<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::require();
$userId = Auth::id();
$pdo = Database::pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::csrfVerify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $category = trim($_POST['category'] ?? 'ALL') ?: 'ALL';
        $amount   = (float)($_POST['amount'] ?? 0);
        $period   = ($_POST['period'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        if ($amount <= 0) {
            flash('error', 'Budget amount must be greater than zero.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO budgets (user_id,category,amount,period) VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE amount = VALUES(amount)'
            );
            $stmt->execute([$userId, $category, $amount, $period]);
            flash('success', 'Budget saved.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM budgets WHERE id=? AND user_id=?')
            ->execute([$id, $userId]);
        flash('success', 'Budget removed.');
    }
    header('Location: ' . url('modules/budget/index.php'));
    exit;
}

// Fetch budgets and compute usage
$budgets = $pdo->prepare('SELECT * FROM budgets WHERE user_id=? ORDER BY period, category');
$budgets->execute([$userId]);
$budgets = $budgets->fetchAll();

$now = new DateTimeImmutable('today');
$mFrom = $now->format('Y-m-01'); $mTo = $now->format('Y-m-t');
$yFrom = $now->format('Y-01-01'); $yTo = $now->format('Y-12-31');

foreach ($budgets as &$b) {
    [$from, $to] = $b['period'] === 'yearly' ? [$yFrom, $yTo] : [$mFrom, $mTo];
    $sql = "SELECT COALESCE(SUM(amount),0) FROM transactions
            WHERE user_id=? AND type='expense' AND date BETWEEN ? AND ?";
    $params = [$userId, $from, $to];
    if ($b['category'] !== 'ALL') {
        $sql .= ' AND category=?';
        $params[] = $b['category'];
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $b['spent'] = (float)$stmt->fetchColumn();
    $b['pct'] = $b['amount'] > 0 ? min(100, round($b['spent'] / (float)$b['amount'] * 100, 1)) : 0;
    $b['remaining'] = max(0, (float)$b['amount'] - $b['spent']);
}
unset($b);

$pageTitle = 'Budgets';
$activeNav = 'budget';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card mb-3">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h4 class="mb-0">Budgets</h4>
      <p class="text-muted small mb-0">Create budgets and track progress.</p>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card"><div class="card-body">
      <h6>Add or update a budget</h6>
      <form method="post">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="save">
        <div class="mb-2">
          <label class="form-label small">Category</label>
          <select name="category" class="form-select">
            <option value="ALL">Total (any category)</option>
            <?php foreach (Tx::CATEGORIES as $c): ?>
              <option value="<?= e($c) ?>"><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small">Period</label>
          <select name="period" class="form-select">
            <option value="monthly">Monthly</option>
            <option value="yearly">Yearly</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small">Amount (<?= e(currentCurrency()) ?>)</label>
          <input type="number" step="0.01" min="0" class="form-control" name="amount" required>
        </div>
        <button class="btn btn-primary w-100"><i class="bi bi-check-lg"></i> Save budget</button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-7">
    <?php if (empty($budgets)): ?>
      <div class="card"><div class="card-body text-center text-muted py-5">
        No budgets yet. Set one on the left to start tracking.
      </div></div>
    <?php else: ?>
    <div class="d-flex flex-column gap-2">
      <?php foreach ($budgets as $b):
        $barClass = $b['pct'] >= 100 ? 'bg-danger' : ($b['pct'] >= 80 ? 'bg-warning' : 'bg-success');
      ?>
      <div class="card"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <strong><?= e($b['category']) ?></strong>
            <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1"><?= e($b['period']) ?></span>
          </div>
          <form method="post" class="d-inline" onsubmit="return confirm('Remove this budget?')">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </div>
        <div class="d-flex justify-content-between small text-muted mt-1">
          <span><?= fmtMoney($b['spent']) ?> spent of <?= fmtMoney((float)$b['amount']) ?></span>
          <span><?= fmtMoney($b['remaining']) ?> left</span>
        </div>
        <div class="progress mt-1" style="height:8px">
          <div class="progress-bar <?= $barClass ?>" role="progressbar"
               style="width: <?= $b['pct'] ?>%"
               aria-valuenow="<?= $b['pct'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <?php if ($b['pct'] >= 80 && $b['pct'] < 100): ?>
          <small class="text-warning">⚠️ Approaching limit (<?= $b['pct'] ?>%)</small>
        <?php elseif ($b['pct'] >= 100): ?>
          <small class="text-danger">🚨 Budget exceeded</small>
        <?php endif; ?>
      </div></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
