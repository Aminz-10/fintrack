# FinTrack — Personal Finance Management

A lightweight, self-hosted finance tracker written in **plain PHP 8 + MySQL +
Bootstrap 5**. Drop it on any cPanel/shared host, point it at a database, and
go. No Composer, no Node, no frameworks.

---

## Features

- Authentication (register / login / logout / remember-me / session timeout)
- Dashboard with daily, weekly, monthly, yearly summaries and Chart.js charts
- Transactions: full CRUD, search, filters, pagination, categories, payment methods
- Reports view (daily / weekly / monthly / yearly) with trend & category charts
- Analytics: top categories, biggest month, average daily spend, savings rate
- Budgets: per-category and total, monthly or yearly, with progress alerts
- Export: CSV, Excel (.xls SpreadsheetML), printable PDF (HTML), TXT, Markdown
- Import: paste / upload `.txt` or `.md` (Apple Notes friendly), auto-detect
  titles, amounts, types, and categories with a preview-and-edit step
- Backup & restore: download/upload JSON (full) or SQL (transactions + budgets)
- Profile settings: display name, currency, language, light/dark theme, password
- Mobile-first responsive UI, dark mode, sticky sidebar, off-canvas on mobile
- CSRF protection on every POST, prepared statements, hardened session cookies,
  per-folder `.htaccess` to deny direct access to includes/config/uploads

---

## Folder structure

```
/
├── assets/              CSS, JS, manifest
├── config/              database.php (auto-creates schema if empty)
├── includes/            auth, header, footer, sidebar, helpers
├── modules/
│   ├── transactions/    CRUD pages
│   ├── reports/         daily / weekly / monthly / yearly views
│   ├── export/          CSV, Excel, PDF, TXT, MD
│   ├── import/          notes parser
│   ├── analytics/       insights
│   ├── budget/          monthly / yearly budgets
│   ├── backup/          download & restore
│   └── profile/         account settings
├── database/
│   ├── schema.sql       reference schema
│   └── sample_data.php  one-shot demo seeder
├── uploads/             empty, write-only, denied via .htaccess
├── backups/             empty, write-only, denied via .htaccess
├── index.php            redirects based on auth
├── login.php
├── register.php
├── logout.php
├── dashboard.php
└── README.md
```

---

## Installation

### Localhost (XAMPP / Laragon / MAMP)

1. Copy the project folder into your local web root, e.g.
   `htdocs/fintrack/` or `www/fintrack/`.
2. Make sure PHP 8.0+ and the MySQL service are running.
3. Open `config/database.php` and edit:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_PORT', '3306');
   define('DB_NAME', 'finance_app');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
4. Visit `http://localhost/fintrack/` in your browser.
   The database and tables are created automatically on first request.
5. Register your account from the login page, or seed demo data:
   ```
   http://localhost/fintrack/database/sample_data.php
   ```
   Default demo login: **`demo@finance.local` / `demo1234`**
   (delete `database/sample_data.php` after seeding).

### Shared hosting (cPanel)

1. Upload the entire project folder via File Manager or FTP into
   `public_html/` (or a subfolder).
2. In cPanel → **MySQL Databases**: create a new database and user, attach the
   user to the database with full privileges.
3. Edit `config/database.php` with the values cPanel gave you.
4. Browse to your domain — the schema is created on first hit.
5. (Optional) `https://yourdomain/database/sample_data.php` to seed demo data,
   then delete the file.

### Apache notes

Each sensitive folder (`config/`, `includes/`, `database/`, `uploads/`,
`backups/`) ships with a `.htaccess` that denies direct access. The root
`.htaccess` adds basic security headers. Make sure `mod_headers` and `mod_rewrite`
are enabled (they are on virtually every cPanel host).

If you use **Nginx**, replicate those denials with `location` blocks:

```nginx
location ~ ^/(config|includes|database|uploads|backups)/ { deny all; }
```

---

## Default admin / demo account

The seeder creates:

```
Email:    demo@finance.local
Password: demo1234
```

Change the password from **Profile → Change password** immediately.

---

## Usage

- **Add a transaction** — Dashboard → "New transaction" or the sidebar.
- **Bulk import from notes** — sidebar → Import. Paste lines like
  `Food - 12`, `Fuel: 50`, `Salary RM3000`. Section headings (`# Expenses`,
  `# Income`) and ISO dates (`2026-05-09`) are recognised.
- **Export a month** — Reports → choose period → "Export this report".
- **Set a budget** — Budgets → choose category, period, amount.
- **Backup** — Backup → Download JSON (recommended) or SQL.
- **Theme** — top right toggle. Per-account theme stored in the user record.

### Apple Notes / iOS Notes round-trip

- **From the app**: Export → Plain Text or Markdown. Open the file and paste
  into Notes — formatting stays clean.
- **Into the app**: open a Notes page, copy the text, and paste it into
  Import. The parser handles `*`/`-` bullets, `:` and `-` separators, and
  `RM`/`$`/`€` currency prefixes.

---

## Security

- Prepared PDO statements everywhere — no string interpolation into SQL.
- `password_hash()` (bcrypt) + `password_verify()`.
- Session cookies: `HttpOnly`, `SameSite=Lax`, `Secure` when on HTTPS.
- 8-hour idle session timeout, regenerated session id on login.
- CSRF token on every POST; pages 419 on mismatch.
- Output via `htmlspecialchars()` (`e()` helper) prevents reflected XSS.
- Upload validation: extension + size cap + `is_uploaded_file()` check.

---

## Configuration knobs

Defined at the top of `config/database.php`:

| Constant            | Default                | Notes                                     |
|---------------------|------------------------|-------------------------------------------|
| `DB_HOST`           | `127.0.0.1`            | Override via env var `DB_HOST` if needed. |
| `DB_PORT`           | `3306`                 |                                           |
| `DB_NAME`           | `finance_app`          | Created automatically if missing.         |
| `APP_NAME`          | `FinTrack`             | Branding in nav and emails.               |
| `APP_TIMEZONE`      | `Asia/Kuala_Lumpur`    | Any valid PHP timezone.                   |
| `APP_BASE_URL`      | `''`                   | e.g. `/fintrack` if served from subfolder.|
| `SESSION_LIFETIME`  | `28800` (8 hours)      | Idle timeout in seconds.                  |

---

## Browser support

Latest two stable versions of Chrome, Firefox, Safari, and Edge. iOS Safari and
Android Chrome are fully supported (responsive layout, off-canvas sidebar,
touch-friendly tap targets).

---

## License

MIT — do whatever you like. No warranty.
