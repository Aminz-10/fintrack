<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::require();
$userId = Auth::id();
$pdo    = Database::pdo();

$format = $_GET['format'] ?? 'json';
$stamp  = date('Ymd_His');

$txStmt = $pdo->prepare('SELECT * FROM transactions WHERE user_id=? ORDER BY date,id');
$txStmt->execute([$userId]);
$transactions = $txStmt->fetchAll();

$bgStmt = $pdo->prepare('SELECT * FROM budgets WHERE user_id=?');
$bgStmt->execute([$userId]);
$budgets = $bgStmt->fetchAll();

if ($format === 'sql') {
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="fintrack_backup_' . $stamp . '.sql"');
    echo "-- FinTrack backup ($stamp) - user_id $userId\n";
    echo "-- Re-import by running this against the same `transactions` and `budgets` schema.\n\n";
    foreach ($transactions as $t) {
        echo "INSERT INTO transactions (user_id,title,amount,type,category,note,payment_method,date) VALUES ("
            . (int)$t['user_id'] . ','
            . sqlq($t['title']) . ','
            . sqlq($t['amount']) . ','
            . sqlq($t['type']) . ','
            . sqlq($t['category']) . ','
            . sqlq($t['note']) . ','
            . sqlq($t['payment_method']) . ','
            . sqlq($t['date'])
            . ");\n";
    }
    foreach ($budgets as $b) {
        echo "INSERT INTO budgets (user_id,category,amount,period) VALUES ("
            . (int)$b['user_id'] . ','
            . sqlq($b['category']) . ','
            . sqlq($b['amount']) . ','
            . sqlq($b['period'])
            . ");\n";
    }
    exit;
}

// Default JSON
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="fintrack_backup_' . $stamp . '.json"');
echo json_encode([
    'app'          => APP_NAME,
    'version'      => APP_VERSION,
    'exported_at'  => date(DATE_ATOM),
    'user_id'      => $userId,
    'transactions' => $transactions,
    'budgets'      => $budgets,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

function sqlq($v): string
{
    if ($v === null) return 'NULL';
    return "'" . str_replace(["\\", "'"], ["\\\\", "''"], (string)$v) . "'";
}
