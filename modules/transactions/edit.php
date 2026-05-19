<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::require();
$userId = Auth::id();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$tx = $id ? Tx::find($userId, $id) : null;
if ($id && !$tx) {
    flash('error', 'Transaction not found.');
    header('Location: ' . url('modules/transactions/index.php'));
    exit;
}

$errors = [];
$data = $tx ?? [
    'title' => '', 'amount' => '', 'type' => 'expense',
    'category' => 'Food', 'note' => '', 'payment_method' => 'Cash',
    'date' => date('Y-m-d'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::csrfVerify();
    $data = array_merge($data, [
        'title'          => trim($_POST['title'] ?? ''),
        'amount'         => $_POST['amount'] ?? '',
        'type'           => $_POST['type'] ?? 'expense',
        'category'       => $_POST['category'] ?? 'Other',
        'note'           => trim($_POST['note'] ?? ''),
        'payment_method' => $_POST['payment_method'] ?? 'Cash',
        'date'           => $_POST['date'] ?? date('Y-m-d'),
    ]);
    if ($data['title'] === '') $errors[] = 'Title is required.';
    if (!is_numeric($data['amount']) || (float)$data['amount'] <= 0) $errors[] = 'Amount must be a positive number.';
    if (!in_array($data['type'], ['income','expense'], true)) $errors[] = 'Invalid type.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) $errors[] = 'Invalid date.';

    if (!$errors) {
        if ($tx) {
            Tx::update($userId, (int)$tx['id'], $data);
            flash('success', 'Transaction updated.');
        } else {
            Tx::create($userId, $data);
            flash('success', 'Transaction added.');
        }
        header('Location: ' . url('modules/transactions/index.php'));
        exit;
    }
}

$pageTitle = $tx ? 'Edit transaction' : 'New transaction';
$activeNav = 'transactions';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card mb-3">
  <div class="card-body d-flex justify-content-between align-items-center">
    <h4 class="mb-0"><?= e($pageTitle) ?></h4>
    <a href="<?= url('modules/transactions/index.php') ?>" class="btn btn-sm btn-outline-secondary bubble">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0">
    <?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
  </ul></div>
<?php endif; ?>

<div class="card"><div class="card-body">
  <form method="post" novalidate>
    <?= Auth::csrfField() ?>
    <div class="row g-3">
      <div class="col-md-8">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" required maxlength="160"
               value="<?= e($data['title']) ?>" autofocus>
      </div>
      <div class="col-md-4">
        <label class="form-label">Amount (<?= e(currentCurrency()) ?>)</label>
        <input type="number" step="0.01" min="0" name="amount" class="form-control" required
               value="<?= e((string)$data['amount']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Type</label>
        <select name="type" class="form-select">
          <option value="expense" <?= $data['type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
          <option value="income"  <?= $data['type'] === 'income'  ? 'selected' : '' ?>>Income</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Category</label>
        <select name="category" class="form-select">
          <?php foreach (Tx::CATEGORIES as $c): ?>
            <option value="<?= e($c) ?>" <?= $data['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Payment method</label>
        <select name="payment_method" class="form-select">
          <?php foreach (Tx::PAYMENT_METHODS as $m): ?>
            <option value="<?= e($m) ?>" <?= $data['payment_method'] === $m ? 'selected' : '' ?>><?= e($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Date</label>
        <input type="date" name="date" class="form-control" required value="<?= e($data['date']) ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Note (optional)</label>
        <textarea name="note" class="form-control" rows="3" maxlength="2000"><?= e($data['note'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
      <a href="<?= url('modules/transactions/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
      <?php if ($tx): ?>
        <form method="post" action="<?= url('modules/transactions/delete.php') ?>" class="ms-auto"
              onsubmit="return confirm('Delete this transaction?')">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="id" value="<?= (int)$tx['id'] ?>">
          <button type="submit" class="btn btn-outline-danger">
            <i class="bi bi-trash"></i> Delete
          </button>
        </form>
      <?php endif; ?>
    </div>
  </form>
</div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
