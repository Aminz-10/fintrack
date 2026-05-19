<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('modules/transactions/index.php'));
    exit;
}

Auth::csrfVerify();
$userId = Auth::id();
$mode   = $_POST['mode'] ?? 'single';

switch ($mode) {

    case 'bulk':
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $deleted = Tx::deleteMany($userId, $ids);
        if ($deleted > 0) {
            flash('success', $deleted . ($deleted === 1 ? ' transaction' : ' transactions') . ' deleted.');
        } else {
            flash('error', 'Nothing was deleted.');
        }
        break;

    case 'range':
        $from = trim((string)($_POST['date_from'] ?? ''));
        $to   = trim((string)($_POST['date_to'] ?? ''));
        if ($from === '' && $to === '') {
            flash('error', 'Please choose a date range.');
            break;
        }
        // Single date supplied → delete that one day.
        if ($from === '') $from = $to;
        if ($to   === '') $to   = $from;
        $deleted = Tx::deleteByRange($userId, $from, $to);
        if ($deleted > 0) {
            $label = $from === $to ? "on $from" : "between $from and $to";
            flash('success', "$deleted " . ($deleted === 1 ? 'transaction' : 'transactions') . " deleted ($label).");
        } else {
            flash('error', 'No transactions found in that date range.');
        }
        break;

    case 'single':
    default:
        $id = (int)($_POST['id'] ?? 0);
        if ($id && Tx::delete($userId, $id)) {
            flash('success', 'Transaction deleted.');
        } else {
            flash('error', 'Could not delete transaction.');
        }
        break;
}

header('Location: ' . url('modules/transactions/index.php'));
exit;
