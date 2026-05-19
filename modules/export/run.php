<?php
/**
 * Streaming exporter for CSV / XLSX / PDF (HTML) / TXT / MD.
 * No external dependencies — XLSX is generated as Excel 2003 SpreadsheetML
 * (.xls) which opens cleanly in Excel, Numbers and LibreOffice.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::require();
$userId = Auth::id();
$cur    = currentCurrency();

$format = strtolower($_GET['format'] ?? 'csv');
$filter = [
    'type'           => $_GET['type'] ?? '',
    'category'       => $_GET['category'] ?? '',
    'payment_method' => $_GET['payment_method'] ?? '',
    'date_from'      => $_GET['date_from'] ?? '',
    'date_to'        => $_GET['date_to']   ?? '',
    'q'              => $_GET['q'] ?? '',
];

$rows    = Tx::list($userId, $filter, 100000);
$summary = Tx::summary($userId, $filter['date_from'] ?: null, $filter['date_to'] ?: null);
$byCat   = Tx::byCategory($userId, $filter['date_from'] ?: null, $filter['date_to'] ?: null);

$dateLabel = ($filter['date_from'] ?: 'all') . '_to_' . ($filter['date_to'] ?: 'all');
$baseName  = 'fintrack_' . $dateLabel;

switch ($format) {
    case 'csv':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $baseName . '.csv"');
        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8 compatibility
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date','Title','Type','Category','Payment Method','Amount','Note']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['date'], $r['title'], $r['type'], $r['category'],
                $r['payment_method'], $r['amount'], $r['note']
            ]);
        }
        fputcsv($out, []);
        fputcsv($out, ['Summary']);
        fputcsv($out, ['Income',  $summary['income']]);
        fputcsv($out, ['Expense', $summary['expense']]);
        fputcsv($out, ['Balance', $summary['balance']]);
        fclose($out);
        exit;

    case 'xlsx':
        // Excel 2003 SpreadsheetML — pure XML, no libraries needed.
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $baseName . '.xls"');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
           . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        echo '<Styles><Style ss:ID="head"><Font ss:Bold="1"/><Interior ss:Color="#E9ECEF" ss:Pattern="Solid"/></Style></Styles>';
        echo '<Worksheet ss:Name="Transactions"><Table>';
        $cols = ['Date','Title','Type','Category','Payment Method','Amount','Note'];
        echo '<Row>';
        foreach ($cols as $c) echo '<Cell ss:StyleID="head"><Data ss:Type="String">' . htmlspecialchars($c) . '</Data></Cell>';
        echo '</Row>';
        foreach ($rows as $r) {
            echo '<Row>';
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($r['date']) . '</Data></Cell>';
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($r['title']) . '</Data></Cell>';
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($r['type']) . '</Data></Cell>';
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($r['category']) . '</Data></Cell>';
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($r['payment_method']) . '</Data></Cell>';
            echo '<Cell><Data ss:Type="Number">' . number_format((float)$r['amount'], 2, '.', '') . '</Data></Cell>';
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$r['note']) . '</Data></Cell>';
            echo '</Row>';
        }
        echo '</Table></Worksheet>';
        echo '<Worksheet ss:Name="Summary"><Table>';
        echo '<Row><Cell ss:StyleID="head"><Data ss:Type="String">Metric</Data></Cell>'
           . '<Cell ss:StyleID="head"><Data ss:Type="String">Amount</Data></Cell></Row>';
        foreach (['Income','Expense','Balance'] as $k) {
            $key = strtolower($k);
            echo '<Row><Cell><Data ss:Type="String">' . $k . '</Data></Cell>';
            echo '<Cell><Data ss:Type="Number">' . number_format((float)$summary[$key], 2, '.', '') . '</Data></Cell></Row>';
        }
        echo '</Table></Worksheet>';
        echo '</Workbook>';
        exit;

    case 'pdf':
        // Print-ready HTML — browsers Cmd/Ctrl+P → Save as PDF.
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html><head><meta charset="utf-8">
        <title>FinTrack Report</title>
        <style>
          @page { size: A4; margin: 18mm; }
          body { font-family: -apple-system, "Segoe UI", Helvetica, Arial, sans-serif; color: #222; }
          h1 { margin: 0 0 4px; font-size: 20px; }
          .meta { color: #666; font-size: 12px; margin-bottom: 16px; }
          .summary { display: flex; gap: 12px; margin: 12px 0 18px; }
          .summary > div { flex: 1; padding: 10px 12px; border-radius: 8px; background: #f4f5f7; }
          .summary strong { display: block; font-size: 18px; }
          table { width: 100%; border-collapse: collapse; font-size: 12px; }
          th, td { border-bottom: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
          th { background: #f4f5f7; }
          .right { text-align: right; }
          .pos { color: #198754; }
          .neg { color: #dc3545; }
          .actions { margin-bottom: 12px; }
          @media print { .actions { display:none; } }
        </style></head><body>
        <div class="actions">
          <button onclick="window.print()">Print / Save as PDF</button>
        </div>
        <h1>FinTrack — Financial Report</h1>
        <div class="meta">
          <?= htmlspecialchars(($filter['date_from'] ?: '—') . ' to ' . ($filter['date_to'] ?: '—')) ?>
          · Generated <?= date('Y-m-d H:i') ?>
        </div>
        <div class="summary">
          <div><span>Income</span><strong class="pos"><?= htmlspecialchars(fmtMoney($summary['income'], $cur)) ?></strong></div>
          <div><span>Expense</span><strong class="neg"><?= htmlspecialchars(fmtMoney($summary['expense'], $cur)) ?></strong></div>
          <div><span>Net Balance</span><strong><?= htmlspecialchars(fmtMoney($summary['balance'], $cur)) ?></strong></div>
        </div>

        <h3>Top expense categories</h3>
        <table><thead><tr><th>Category</th><th class="right">Total</th></tr></thead><tbody>
        <?php foreach (array_slice($byCat, 0, 10) as $c): ?>
          <tr><td><?= htmlspecialchars($c['category']) ?></td>
              <td class="right"><?= htmlspecialchars(fmtMoney((float)$c['total'], $cur)) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>

        <h3 style="margin-top:18px">Transactions</h3>
        <table><thead><tr>
          <th>Date</th><th>Title</th><th>Category</th><th>Method</th><th class="right">Amount</th>
        </tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['date']) ?></td>
            <td><?= htmlspecialchars($r['title']) ?></td>
            <td><?= htmlspecialchars($r['category']) ?></td>
            <td><?= htmlspecialchars($r['payment_method']) ?></td>
            <td class="right <?= $r['type'] === 'income' ? 'pos' : 'neg' ?>">
              <?= $r['type'] === 'income' ? '+' : '−' ?><?= htmlspecialchars(fmtMoney((float)$r['amount'], $cur)) ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody></table>
        </body></html><?php
        exit;

    case 'txt':
    case 'md':
        $isMd = $format === 'md';
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $baseName . '.' . $format . '"');
        $h1 = $isMd ? '# ' : '';
        $h2 = $isMd ? '## ' : '';
        $bullet = $isMd ? '- ' : '* ';
        $rangeLabel = ($filter['date_from'] ?: '—') . ' to ' . ($filter['date_to'] ?: '—');
        echo $h1 . "FinTrack Report — $rangeLabel\n\n";

        // Group by date
        $byDate = [];
        foreach ($rows as $r) $byDate[$r['date']][] = $r;
        ksort($byDate);

        foreach ($byDate as $d => $list) {
            echo "\n" . $h2 . $d . "\n\n";
            $expense = []; $income = [];
            foreach ($list as $t) {
                if ($t['type'] === 'income') $income[] = $t; else $expense[] = $t;
            }
            if ($expense) {
                echo $isMd ? "**Expenses**\n\n" : "Expenses\n";
                foreach ($expense as $t) {
                    echo $bullet . $t['title'] . ' (' . $t['category'] . '): ' . $cur . number_format((float)$t['amount'], 2) . "\n";
                }
                echo "\n";
            }
            if ($income) {
                echo $isMd ? "**Income**\n\n" : "Income\n";
                foreach ($income as $t) {
                    echo $bullet . $t['title'] . ' (' . $t['category'] . '): ' . $cur . number_format((float)$t['amount'], 2) . "\n";
                }
                echo "\n";
            }
        }

        echo "\n" . $h2 . "Summary\n\n";
        echo $bullet . "Total income: $cur" . number_format($summary['income'],  2) . "\n";
        echo $bullet . "Total expense: $cur" . number_format($summary['expense'], 2) . "\n";
        echo $bullet . "Balance: $cur" . number_format($summary['balance'], 2) . "\n";
        exit;
}

http_response_code(400);
echo 'Unknown format';
