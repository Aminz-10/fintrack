<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::require();
$userId = Auth::id();
$pdo = Database::pdo();

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::csrfVerify();
    $action = $_POST['action'] ?? '';

    if ($action === 'restore' && !empty($_FILES['backup']['tmp_name']) && is_uploaded_file($_FILES['backup']['tmp_name'])) {
        if ($_FILES['backup']['size'] > 5 * 1024 * 1024) {
            flash('error', 'Backup file too large (max 5 MB).');
            header('Location: ' . url('modules/backup/index.php')); exit;
        }
        $name = $_FILES['backup']['name'];
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'json') {
            flash('error', 'Only JSON backups are accepted.');
            header('Location: ' . url('modules/backup/index.php')); exit;
        }
        $raw = file_get_contents($_FILES['backup']['tmp_name']);
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['transactions'])) {
            flash('error', 'Invalid backup file.');
            header('Location: ' . url('modules/backup/index.php')); exit;
        }
        $imported = 0;
        $pdo->beginTransaction();
        try {
            // Optionally clear existing data when "replace" was selected.
            if (!empty($_POST['replace'])) {
                $pdo->prepare('DELETE FROM transactions WHERE user_id=?')->execute([$userId]);
                $pdo->prepare('DELETE FROM budgets WHERE user_id=?')->execute([$userId]);
            }
            foreach ($data['transactions'] as $t) {
                Tx::create($userId, [
                    'title'          => $t['title'] ?? 'Untitled',
                    'amount'         => $t['amount'] ?? 0,
                    'type'           => $t['type'] ?? 'expense',
                    'category'       => $t['category'] ?? 'Other',
                    'note'           => $t['note'] ?? null,
                    'payment_method' => $t['payment_method'] ?? 'Cash',
                    'date'           => $t['date'] ?? date('Y-m-d'),
                ]);
                $imported++;
            }
            if (!empty($data['budgets'])) {
                $bs = $pdo->prepare('INSERT IGNORE INTO budgets (user_id,category,amount,period) VALUES (?,?,?,?)');
                foreach ($data['budgets'] as $b) {
                    $bs->execute([$userId, $b['category'] ?? 'ALL', $b['amount'] ?? 0, $b['period'] ?? 'monthly']);
                }
            }
            $pdo->commit();
            flash('success', "Restored $imported transactions.");
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('error', 'Restore failed: ' . $ex->getMessage());
        }
        header('Location: ' . url('modules/backup/index.php'));
        exit;
    }
}

// Counts for display
$txCount  = (int)$pdo->query('SELECT COUNT(*) FROM transactions WHERE user_id=' . $userId)->fetchColumn();
$bgCount  = (int)$pdo->query('SELECT COUNT(*) FROM budgets WHERE user_id=' . $userId)->fetchColumn();

$pageTitle = 'Backup & restore';
$activeNav = 'backup';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card mb-3">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h4 class="mb-0">Backup &amp; restore</h4>
      <p class="text-muted small mb-0">Download or restore a backup of your data.</p>
    </div>
    <div class="small text-muted d-none d-md-block"><?= number_format($txCount) ?> tx · <?= number_format($bgCount) ?> budgets</div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card h-100"><div class="card-body">
      <h6><i class="bi bi-cloud-arrow-down"></i> Backup</h6>
      <p class="text-muted small">Download a complete copy of your data. Store it somewhere safe.</p>
      <ul class="small">
        <li><?= number_format($txCount) ?> transactions</li>
        <li><?= number_format($bgCount) ?> budgets</li>
      </ul>
      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-primary" href="<?= url('modules/backup/export.php?format=json') ?>">
          <i class="bi bi-filetype-json"></i> Download JSON
        </a>
        <a class="btn btn-outline-secondary" href="<?= url('modules/backup/export.php?format=sql') ?>">
          <i class="bi bi-file-earmark-code"></i> Download SQL
        </a>
      </div>
    </div></div>
  </div>

  <div class="col-md-6">
    <div class="card h-100"><div class="card-body">
      <h6><i class="bi bi-cloud-arrow-up"></i> Restore</h6>
      <form method="post" enctype="multipart/form-data">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="restore">
        <input type="file" name="backup" class="form-control mb-2" accept=".json" required>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="replace" name="replace">
          <label class="form-check-label small" for="replace">
            Replace existing data (delete then restore)
          </label>
        </div>
        <button class="btn btn-warning"
                onclick="return confirm('Restore from backup?')">
          <i class="bi bi-arrow-counterclockwise"></i> Restore
        </button>
      </form>
    </div></div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
