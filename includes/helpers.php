<?php
/**
 * Shared domain helpers — queries, summaries, parsing.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

class Tx
{
    public const CATEGORIES = [
        'Food', 'Transport', 'Fuel', 'Shopping', 'Bills',
        'Entertainment', 'Health', 'Education', 'Travel',
        'Salary', 'Investment', 'Gift', 'Other',
    ];

    public const PAYMENT_METHODS = [
        'Cash', 'Debit Card', 'Credit Card', 'E-Wallet', 'Bank Transfer',
    ];

    /** Filtered list of transactions for a user. */
    public static function list(int $userId, array $f = [], int $limit = 25, int $offset = 0): array
    {
        [$where, $params] = self::buildFilter($userId, $f);
        $sql = "SELECT * FROM transactions WHERE $where ORDER BY date DESC, id DESC LIMIT $limit OFFSET $offset";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(int $userId, array $f = []): int
    {
        [$where, $params] = self::buildFilter($userId, $f);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM transactions WHERE $where");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /** Returns ['where' => sql, 'params' => array]. */
    private static function buildFilter(int $userId, array $f): array
    {
        $where = ['user_id = ?'];
        $params = [$userId];
        if (!empty($f['type']) && in_array($f['type'], ['income', 'expense'], true)) {
            $where[] = 'type = ?';
            $params[] = $f['type'];
        }
        if (!empty($f['category'])) {
            $where[] = 'category = ?';
            $params[] = $f['category'];
        }
        if (!empty($f['payment_method'])) {
            $where[] = 'payment_method = ?';
            $params[] = $f['payment_method'];
        }
        if (!empty($f['date_from'])) {
            $where[] = 'date >= ?';
            $params[] = $f['date_from'];
        }
        if (!empty($f['date_to'])) {
            $where[] = 'date <= ?';
            $params[] = $f['date_to'];
        }
        if (!empty($f['min_amount'])) {
            $where[] = 'amount >= ?';
            $params[] = (float)$f['min_amount'];
        }
        if (!empty($f['max_amount'])) {
            $where[] = 'amount <= ?';
            $params[] = (float)$f['max_amount'];
        }
        if (!empty($f['q'])) {
            $where[] = '(title LIKE ? OR note LIKE ?)';
            $params[] = '%' . $f['q'] . '%';
            $params[] = '%' . $f['q'] . '%';
        }
        return [implode(' AND ', $where), $params];
    }

    public static function find(int $userId, int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM transactions WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $userId, array $d): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO transactions (user_id,title,amount,type,category,note,payment_method,date)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $userId,
            trim($d['title']),
            round((float)$d['amount'], 2),
            $d['type'] === 'income' ? 'income' : 'expense',
            $d['category'] ?: 'Other',
            $d['note'] ?? null,
            $d['payment_method'] ?: 'Cash',
            $d['date'] ?: date('Y-m-d'),
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $userId, int $id, array $d): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE transactions SET title=?,amount=?,type=?,category=?,note=?,payment_method=?,date=?
             WHERE id=? AND user_id=?'
        );
        return $stmt->execute([
            trim($d['title']),
            round((float)$d['amount'], 2),
            $d['type'] === 'income' ? 'income' : 'expense',
            $d['category'] ?: 'Other',
            $d['note'] ?? null,
            $d['payment_method'] ?: 'Cash',
            $d['date'] ?: date('Y-m-d'),
            $id,
            $userId,
        ]);
    }

    public static function delete(int $userId, int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM transactions WHERE id=? AND user_id=?');
        return $stmt->execute([$id, $userId]);
    }

    /** Delete a set of transactions by id. Returns the number of rows removed. */
    public static function deleteMany(int $userId, array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($v) => $v > 0)));
        if (!$ids) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "DELETE FROM transactions WHERE user_id=? AND id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$userId], $ids));
        return $stmt->rowCount();
    }

    /**
     * Delete every transaction within a date range (inclusive) for the user.
     * If $from === $to, this is effectively "delete by single date".
     * Returns the number of rows removed.
     */
    public static function deleteByRange(int $userId, string $from, string $to): int
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return 0;
        }
        if ($from > $to) [$from, $to] = [$to, $from];
        $stmt = Database::pdo()->prepare(
            'DELETE FROM transactions WHERE user_id=? AND date BETWEEN ? AND ?'
        );
        $stmt->execute([$userId, $from, $to]);
        return $stmt->rowCount();
    }

    // ---------- Summaries ----------

    /** Returns ['income' => x, 'expense' => y, 'balance' => x-y]. */
    public static function summary(int $userId, ?string $from = null, ?string $to = null): array
    {
        $sql = 'SELECT type, COALESCE(SUM(amount),0) AS total
                FROM transactions WHERE user_id=?';
        $params = [$userId];
        if ($from) { $sql .= ' AND date >= ?'; $params[] = $from; }
        if ($to)   { $sql .= ' AND date <= ?'; $params[] = $to; }
        $sql .= ' GROUP BY type';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $income = 0.0; $expense = 0.0;
        foreach ($stmt->fetchAll() as $row) {
            if ($row['type'] === 'income') $income = (float)$row['total'];
            else $expense = (float)$row['total'];
        }
        return ['income' => $income, 'expense' => $expense, 'balance' => $income - $expense];
    }

    /** Spending grouped by category (expenses only) for the date range. */
    public static function byCategory(int $userId, ?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT category, COALESCE(SUM(amount),0) AS total
                FROM transactions
                WHERE user_id=? AND type='expense'";
        $params = [$userId];
        if ($from) { $sql .= ' AND date >= ?'; $params[] = $from; }
        if ($to)   { $sql .= ' AND date <= ?'; $params[] = $to; }
        $sql .= ' GROUP BY category ORDER BY total DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Daily totals for charting. Returns labels, income[], expense[]. */
    public static function dailySeries(int $userId, string $from, string $to): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT date, type, COALESCE(SUM(amount),0) AS total
             FROM transactions
             WHERE user_id=? AND date BETWEEN ? AND ?
             GROUP BY date, type ORDER BY date ASC"
        );
        $stmt->execute([$userId, $from, $to]);
        $rows = $stmt->fetchAll();

        $labels = [];
        $income = [];
        $expense = [];
        $period = new DatePeriod(
            new DateTimeImmutable($from),
            new DateInterval('P1D'),
            (new DateTimeImmutable($to))->modify('+1 day')
        );
        foreach ($period as $d) {
            $key = $d->format('Y-m-d');
            $labels[$key] = $d->format('M d');
            $income[$key] = 0.0;
            $expense[$key] = 0.0;
        }
        foreach ($rows as $r) {
            if (!isset($labels[$r['date']])) continue;
            if ($r['type'] === 'income') $income[$r['date']] = (float)$r['total'];
            else $expense[$r['date']] = (float)$r['total'];
        }
        return [
            'labels'  => array_values($labels),
            'income'  => array_values($income),
            'expense' => array_values($expense),
        ];
    }

    /** Monthly totals across a year. */
    public static function monthlySeries(int $userId, int $year): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT MONTH(date) AS m, type, COALESCE(SUM(amount),0) AS total
             FROM transactions
             WHERE user_id=? AND YEAR(date)=?
             GROUP BY MONTH(date), type ORDER BY m ASC"
        );
        $stmt->execute([$userId, $year]);
        $income = array_fill(1, 12, 0.0);
        $expense = array_fill(1, 12, 0.0);
        foreach ($stmt->fetchAll() as $r) {
            if ($r['type'] === 'income') $income[(int)$r['m']] = (float)$r['total'];
            else $expense[(int)$r['m']] = (float)$r['total'];
        }
        return [
            'labels'  => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
            'income'  => array_values($income),
            'expense' => array_values($expense),
        ];
    }

    public static function recent(int $userId, int $limit = 5): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM transactions WHERE user_id=? ORDER BY date DESC, id DESC LIMIT ' . (int)$limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}

/**
 * Lightweight parser that turns a freeform notes file (Apple Notes)
 * into structured transaction rows.
 *
 * Recognised patterns (case-insensitive):
 *   "Food - 12"
 *   "Fuel: 50"
 *   "Salary RM3000"
 *   "* Coffee: 8"
 *   "- groceries 45.50"
 *
 * Lines starting with "Total", "Balance", "#", or section headers are skipped.
 */
class NotesParser
{
    private const INCOME_KEYWORDS = [
        'salary', 'wage', 'income', 'bonus', 'freelance', 'refund',
        'dividend', 'interest', 'investment', 'gift received', 'commission',
        'gaji', 'upah', 'pendapatan', 'dividen', 'rebat', 'cashback',
    ];

    /**
     * Category keyword hints. Order matters — first matching category wins.
     *
     * Two kinds of hints are supported:
     *   - Single-word hints: matched as a whole word ("air" matches "air mineral"
     *     but NOT "airport" or "airpods"). Use these for short or ambiguous terms.
     *   - Multi-word hints (containing a space): plain substring match anywhere
     *     in the title. Use these for distinctive brand / phrase tokens.
     */
    private const CATEGORY_HINTS = [
        // Fuel first so "minyak" → Fuel before being claimed elsewhere.
        'Fuel' => [
            'fuel','petrol','gas','diesel','gasoline',
            'minyak','petronas','shell','caltex','bhp',
        ],
        'Transport' => [
            'transport','grab','gojek','taxi','uber','bus','bas','train','mrt','lrt','ktm','ets','komuter',
            'toll','tol','parking','parkir','tiket','ticket','motor','motorcycle','kereta','car',
        ],
        'Food' => [
            // English staples
            'food','lunch','dinner','breakfast','sahur','iftar','buka','meal','restaurant','restoran','cafe','kafe',
            'coffee','tea','snack','grocer','grocery','market','pasar',
            // Malay / Indonesian dishes & ingredients
            'nasi','ayam','ikan','daging','sayur','telur','roti','mee','mihun','bihun','kuetiau','kuey','pasta',
            'gepuk','gepok','kukus','goreng','bakar','panggang','rendang','sambal','laksa','lemak','kerabu','soto','satay','sate',
            'burger','pizza','sushi','ramen','sandwich','wrap','salad','soup','sup','dessert',
            // Eateries & food delivery
            'mamak','warung','kedai','foodcourt','foodpanda','grabfood','shopeefood','airasia food','dahmakan',
            // Brands
            'mcdonald','mcd','kfc','starbucks','tealive','zus','chatime','subway','dominos','pizzahut','marrybrown','old town','oldtown','secret recipe','texas chicken',
            // Drinks (Malay: "air" = water/drink)
            'kopi','teh','air','minum','minuman','drink','juice','jus','milo','horlick','horlicks','smoothie','mineral','soda','syrup','sirap',
        ],
        'Shopping' => [
            'shop','clothes','baju','seluar','kasut','shoe','shopee','lazada','amazon','tiktok',
            'mall','centre','center','watson','guardian','aeon','tesco','lulu','mydin','popular',
        ],
        'Bills' => [
            'bill','bil','electric','elektrik','tnb','syabas','airselangor',
            'internet','wifi','unifi','maxis','digi','celcom','umobile','yes',
            'phone','telefon','rent','sewa','utilities','utility','tax','cukai','roadtax','sticker','stiker','insurance','insurans','takaful',
        ],
        'Entertainment' => [
            'movie','wayang','cinema','tgv','gsc','mbo','netflix','spotify','youtube','disney','iflix','astro',
            'game','playstation','xbox','steam','nintendo','concert','konsert','karaoke','bowling','arcade',
        ],
        'Health' => [
            'hospital','klinik','clinic','pharmacy','farmasi','medic','medicine','ubat','doctor','dentist','dental',
            'gym','yoga','fitness',
        ],
        'Education' => [
            'school','sekolah','tuition','tusyen','course','kursus','book','buku','class','kelas',
            'university','universiti','college','kolej',
        ],
        'Travel' => [
            'hotel','flight','penerbangan','airbnb','vacation','holiday','cuti','trip','airport',
            'airasia','firefly','batik air','malaysia airlines','mas','traveloka','agoda','booking',
        ],
        'Salary' => [
            'salary','gaji','wage','payslip','epf','kwsp','perkeso','socso','eis',
        ],
        'Investment' => [
            'stock','saham','crypto','bitcoin','ethereum','etf','dividend','dividen','invest','asnb','asb','tabung haji','unit trust',
        ],
        'Gift' => [
            'gift','hadiah','present','donation','derma','sedekah','zakat','duit raya','angpow','angpao',
        ],
    ];

    /**
     * @return array<int, array{title:string,amount:float,type:string,category:string,date:string,note:string}>
     */
    public static function parse(string $text, ?string $defaultDate = null): array
    {
        $defaultDate = $defaultDate ?: date('Y-m-d');
        $currentDate = $defaultDate;
        $section = null; // 'income' | 'expense' | null
        $out = [];

        $lines = preg_split('/\r\n|\r|\n/', $text);
        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') continue;

            // Section markers
            if (preg_match('/^#+\s*expense/i', $line) || preg_match('/^expenses?:?$/i', $line)) {
                $section = 'expense'; continue;
            }
            if (preg_match('/^#+\s*income/i', $line) || preg_match('/^incomes?:?$/i', $line)) {
                $section = 'income'; continue;
            }

            // Word-form date heading: "4-10 may 2026", "May 4-10, 2026", "4 May 2026",
            // "May 2026", "4/5/2026", etc. If matched, this line is purely a heading —
            // use it as the date context for following lines and skip it.
            $hdr = self::detectDateHeading($line);
            if ($hdr !== null) {
                $currentDate = $hdr;
                continue;
            }

            // Legacy: inline ISO date "2026-05-09" anywhere in line sets context
            // (but the rest of the line is still parsed as a transaction).
            if (preg_match('/(\d{4}-\d{1,2}-\d{1,2})/', $line, $dm)) {
                $d = self::normaliseDate($dm[1]);
                if ($d) $currentDate = $d;
            }

            // Skip totals / balance / pure headings
            if (preg_match('/^(total|balance|summary|notes?)\b/i', $line)) continue;
            if (preg_match('/^#/', $line)) continue;

            // Parse "<title> <sep> <amount>"
            $parsed = self::parseLine($line);
            if (!$parsed) continue;

            [$title, $amount] = $parsed;
            $type = $section ?? self::guessType($title);
            $category = self::guessCategory($title, $type);

            $out[] = [
                'title'    => $title,
                'amount'   => $amount,
                'type'     => $type,
                'category' => $category,
                'date'     => $currentDate,
                'note'     => 'Imported',
            ];
        }
        return $out;
    }

    private static function parseLine(string $line): ?array
    {
        // Strip leading bullets/markers
        $line = preg_replace('/^[\s\-\*••▪]+/u', '', $line);
        // Match "Title <sep> Amount" or "Title Amount"
        if (preg_match('/^(.+?)\s*[\:\-–—]\s*[A-Z$€£¥₹₽]*\s*([0-9]+(?:[\.,][0-9]{1,2})?)\s*$/u', $line, $m)) {
            $title = trim($m[1]);
            $amount = (float)str_replace(',', '.', $m[2]);
            if ($title !== '' && $amount > 0) return [$title, $amount];
        }
        if (preg_match('/^(.+?)\s+[A-Z$€£¥₹₽]{0,3}\s*([0-9]+(?:[\.,][0-9]{1,2})?)\s*$/u', $line, $m)) {
            $title = trim($m[1]);
            $amount = (float)str_replace(',', '.', $m[2]);
            if ($title !== '' && $amount > 0) return [$title, $amount];
        }
        return null;
    }

    private static function guessType(string $title): string
    {
        $low    = strtolower($title);
        $tokens = self::tokenize($title);
        foreach (self::INCOME_KEYWORDS as $k) {
            if (str_contains($k, ' ')) {
                if (str_contains($low, $k)) return 'income';
            } else {
                if (in_array($k, $tokens, true)) return 'income';
            }
        }
        return 'expense';
    }

    private static function guessCategory(string $title, string $type): string
    {
        $low    = strtolower($title);
        $tokens = self::tokenize($title);
        foreach (self::CATEGORY_HINTS as $cat => $hints) {
            foreach ($hints as $h) {
                if (str_contains($h, ' ')) {
                    // Multi-word hint: plain substring match.
                    if (str_contains($low, $h)) return $cat;
                } else {
                    // Single-word hint: whole-word match so "air" doesn't hit "airport".
                    if (in_array($h, $tokens, true)) return $cat;
                }
            }
        }
        return $type === 'income' ? 'Salary' : 'Other';
    }

    /** Lower-cased word tokens; non-letter chars split. Used by guess* matchers. */
    private static function tokenize(string $text): array
    {
        $parts = preg_split('/[^a-zA-Z]+/u', strtolower($text)) ?: [];
        return array_values(array_filter($parts, static fn ($s) => $s !== ''));
    }

    private static function normaliseDate(string $d): ?string
    {
        $ts = strtotime($d);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /**
     * Detect a "date heading" line. Returns ISO date (start of range for ranges)
     * or null if the line is not a heading.
     *
     * Recognises (case-insensitive):
     *   - "4-10 may 2026", "11-17 May 2026" (day-day month year — uses start day)
     *   - "May 4-10, 2026", "May 4 - 10 2026"
     *   - "4 May 2026", "4th May 2026"
     *   - "May 4, 2026"
     *   - "May 2026" (uses day 1)
     *   - "4/5/2026", "4-5-2026" (D/M/YYYY)
     *   - "2026-05-09" (ISO, when the whole line is the date)
     * Lines containing a currency-prefixed amount (RM12, $7.50, etc.) are NOT
     * treated as headings — those are transactions.
     */
    private static function detectDateHeading(string $line): ?string
    {
        // Bail if the line clearly contains an amount with currency (it's a transaction).
        if (preg_match('/(RM|MYR|USD|EUR|GBP|SGD|IDR|\$|€|£|¥|₹|₽)\s*\d/i', $line)) {
            return null;
        }

        // Strip leading bullets / common label prefixes.
        $l = preg_replace('/^[\s\*\-•▪]+/u', '', $line);
        $l = preg_replace('/^(date|week|day|period)\s*[:\-]\s*/i', '', $l);
        $l = trim((string)$l);
        if ($l === '') return null;

        // ISO date alone
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})\s*$/', $l, $m)) {
            return self::makeDate((int)$m[1], (int)$m[2], (int)$m[3]);
        }

        // D-D Month YYYY   (e.g. "4-10 may 2026", "11 - 17 May 2026") — use start day.
        if (preg_match('/^(\d{1,2})(?:st|nd|rd|th)?\s*[-–—]\s*(\d{1,2})(?:st|nd|rd|th)?\s+([A-Za-z\.]+)\s+(\d{4})\s*$/u', $l, $m)) {
            $mo = self::monthFrom($m[3]);
            if ($mo) return self::makeDate((int)$m[4], $mo, (int)$m[1]);
        }

        // Month D-D[,] YYYY   (e.g. "May 4-10, 2026") — use start day.
        if (preg_match('/^([A-Za-z\.]+)\s+(\d{1,2})(?:st|nd|rd|th)?\s*[-–—]\s*(\d{1,2})(?:st|nd|rd|th)?[,\s]+(\d{4})\s*$/u', $l, $m)) {
            $mo = self::monthFrom($m[1]);
            if ($mo) return self::makeDate((int)$m[4], $mo, (int)$m[2]);
        }

        // D Month YYYY   (e.g. "4 May 2026", "4th may 2026")
        if (preg_match('/^(\d{1,2})(?:st|nd|rd|th)?\s+([A-Za-z\.]+)\s+(\d{4})\s*$/u', $l, $m)) {
            $mo = self::monthFrom($m[2]);
            if ($mo) return self::makeDate((int)$m[3], $mo, (int)$m[1]);
        }

        // Month D[,] YYYY   (e.g. "May 4, 2026", "May 4 2026")
        if (preg_match('/^([A-Za-z\.]+)\s+(\d{1,2})(?:st|nd|rd|th)?[,\s]+(\d{4})\s*$/u', $l, $m)) {
            $mo = self::monthFrom($m[1]);
            if ($mo) return self::makeDate((int)$m[3], $mo, (int)$m[2]);
        }

        // Month YYYY   (e.g. "May 2026") — use day 1.
        if (preg_match('/^([A-Za-z\.]+)\s+(\d{4})\s*$/u', $l, $m)) {
            $mo = self::monthFrom($m[1]);
            if ($mo) return self::makeDate((int)$m[2], $mo, 1);
        }

        // D/M/YYYY or D-M-YYYY (numeric only)
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\s*$/', $l, $m)) {
            return self::makeDate((int)$m[3], (int)$m[2], (int)$m[1]);
        }

        return null;
    }

    private static function monthFrom(string $word): ?int
    {
        static $months = [
            'jan' => 1,  'feb' => 2,  'mar' => 3,  'apr' => 4,
            'may' => 5,  'jun' => 6,  'jul' => 7,  'aug' => 8,
            'sep' => 9,  'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];
        // Normalise: lowercase, drop trailing dot, take first 3 letters.
        $key = strtolower(rtrim($word, '.'));
        $key = substr($key, 0, 3);
        return $months[$key] ?? null;
    }

    private static function makeDate(int $y, int $m, int $d): ?string
    {
        if ($y < 1900 || $y > 2999) return null;
        if (!checkdate($m, $d, $y)) return null;
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
}
