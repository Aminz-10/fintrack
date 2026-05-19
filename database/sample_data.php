<?php
/**
 * One-shot script that seeds a demo user and ~60 random transactions.
 * Run from the browser ONCE (then delete it from your server) or from CLI:
 *
 *   php database/sample_data.php
 *
 * Default login created:
 *   Email:    demo@finance.local
 *   Password: demo1234
 */
require_once __DIR__ . '/../config/database.php';

$pdo = Database::pdo();

$email = 'demo@finance.local';
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
$userId = (int)$stmt->fetchColumn();

if (!$userId) {
    $pdo->prepare('INSERT INTO users (name,email,password,currency) VALUES (?,?,?,?)')
        ->execute([
            'Demo User',
            $email,
            password_hash('demo1234', PASSWORD_DEFAULT),
            'RM',
        ]);
    $userId = (int)$pdo->lastInsertId();
    echo "Created demo user (id=$userId)\n";
} else {
    echo "Demo user already exists (id=$userId)\n";
}

$samples = [
    ['Salary',           3200.00, 'income',  'Salary'],
    ['Freelance project', 850.00, 'income',  'Investment'],
    ['Groceries',          78.40, 'expense', 'Food'],
    ['Coffee',              7.50, 'expense', 'Food'],
    ['Petrol',             65.00, 'expense', 'Fuel'],
    ['Internet bill',     120.00, 'expense', 'Bills'],
    ['Electric bill',      89.50, 'expense', 'Bills'],
    ['Movie tickets',      48.00, 'expense', 'Entertainment'],
    ['New shoes',         215.00, 'expense', 'Shopping'],
    ['Lunch',              18.00, 'expense', 'Food'],
    ['Grab ride',          12.30, 'expense', 'Transport'],
    ['Spotify',            14.90, 'expense', 'Entertainment'],
    ['Pharmacy',           33.20, 'expense', 'Health'],
    ['Book',               42.00, 'expense', 'Education'],
];

$ins = $pdo->prepare(
    'INSERT INTO transactions (user_id,title,amount,type,category,note,payment_method,date)
     VALUES (?,?,?,?,?,?,?,?)'
);

$inserted = 0;
for ($i = 0; $i < 60; $i++) {
    $s = $samples[array_rand($samples)];
    $date = (new DateTimeImmutable('today'))
        ->modify('-' . random_int(0, 60) . ' days')
        ->format('Y-m-d');
    $ins->execute([
        $userId,
        $s[0],
        $s[1],
        $s[2],
        $s[3],
        null,
        ['Cash','Debit Card','Credit Card','E-Wallet','Bank Transfer'][random_int(0,4)],
        $date,
    ]);
    $inserted++;
}
echo "Inserted $inserted sample transactions.\n";
echo "Login: $email / demo1234\n";
