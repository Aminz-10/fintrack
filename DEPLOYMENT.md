# Deploying FinTrack from GitHub

This repo is set up so every `git push` to `main` auto-deploys to a PHP/MySQL
host over FTP. The workflow lives in `.github/workflows/deploy.yml`.

This guide uses **InfinityFree** (free PHP 8 + MySQL + FTP) as the example
host, but the same workflow works for any host that exposes FTP/FTPS/SFTP
(Hostinger, Namecheap, IONOS, A2 Hosting, etc.).

---

## 1. Push the project to GitHub

```powershell
cd c:\xampp\htdocs\Calculator
git init
git branch -M main
git add .
git commit -m "Initial FinTrack commit"

# Create an empty repo at https://github.com/new (private is fine), then:
git remote add origin https://github.com/<your-user>/<your-repo>.git
git push -u origin main
```

The `.gitignore` already excludes `config/database.local.php`, `uploads/`,
`backups/`, and other things you don't want in the repo.

---

## 2. Create a free hosting account + database

Sign up at [infinityfree.net](https://infinityfree.net) (or any PHP host).

1. **Create a website** — pick a free subdomain like `fintrack.rf.gd`, or
   attach your own domain later.
2. **Create a MySQL database** in *Control Panel → MySQL Databases*. Note
   the four values it gives you:
   - DB host  → e.g. `sql300.infinityfree.com`
   - DB name  → e.g. `if0_12345678_finance`
   - DB user  → e.g. `if0_12345678`
   - DB pass  → the password you set
3. **Find your FTP credentials** in *Control Panel → FTP Accounts*:
   - FTP host → e.g. `ftpupload.net`
   - FTP user → e.g. `if0_12345678`
   - FTP pass → same as your hosting password (or set a new FTP password)

---

## 3. Create the production config file *on the server*

Using the FTP client (FileZilla) or the host's File Manager, navigate to
`/htdocs/config/` and **create a new file** `database.local.php` with:

```php
<?php
define('DB_HOST', 'sql300.infinityfree.com');
define('DB_NAME', 'if0_12345678_finance');
define('DB_USER', 'if0_12345678');
define('DB_PASS', 'YOUR-REAL-PASSWORD');
define('APP_BASE_URL', '');  // empty if FinTrack is at the domain root
```

The file `config/database.local.example.php` already in the repo shows the
exact format. The local file is **never** uploaded by the workflow (it's
listed in the FTP `exclude:` block) and never committed to git (it's in
`.gitignore`).

---

## 4. Add the four GitHub secrets

On your GitHub repo go to **Settings → Secrets and variables → Actions →
New repository secret** and add:

| Secret name      | Value                              |
|------------------|------------------------------------|
| `FTP_HOST`       | e.g. `ftpupload.net`               |
| `FTP_USER`       | your FTP username                  |
| `FTP_PASSWORD`   | your FTP password                  |

If your host uses a non-default port, FTPS, or a different remote folder,
also add these under **Variables** (same screen, "Variables" tab):

| Variable name     | Default | Set to                                  |
|-------------------|---------|-----------------------------------------|
| `FTP_PORT`        | `21`    | e.g. `22` for SFTP                      |
| `FTP_PROTOCOL`    | `ftp`   | `ftps` or `sftp`                        |
| `FTP_REMOTE_DIR`  | `/htdocs/` | e.g. `/public_html/` (Hostinger/cPanel) |

---

## 5. Push to deploy

```powershell
git add .
git commit -m "Initial deploy"
git push
```

Watch the deploy in real time at **GitHub repo → Actions**. The first run
uploads every file; subsequent runs only sync changes (the action keeps a
manifest on the server).

When it finishes, open your site URL — the database schema is created
automatically on the first request.

---

## 6. Optional: seed demo data (then delete the seeder)

Visit `https://your-domain/database/sample_data.php` once to load the
demo user (`demo@finance.local` / `demo1234`). Then delete that file
from the server via FTP — or uncomment the matching line in `.gitignore`
so it stops shipping in future deploys.

---

## 7. Troubleshooting

| Symptom                                | Likely cause                                                                 |
|----------------------------------------|------------------------------------------------------------------------------|
| "Database connection failed."          | `database.local.php` missing or wrong credentials                            |
| Login redirects loop                   | `APP_BASE_URL` mismatch — set it to `''` for a domain root                   |
| Pages 404                              | `.htaccess` missing or `mod_rewrite` not enabled on the host                 |
| Files don't update after push          | Check Actions tab — secret typo, wrong port, or `dangerous-clean-slate` off  |
| FTP times out                          | Try `FTP_PROTOCOL=ftps` (port 21) or `sftp` (port 22)                        |

---

## 8. Switching hosts later

You only need to change the GitHub secrets — the workflow is host-agnostic.
For cPanel hosts (Hostinger etc.), set `FTP_REMOTE_DIR = /public_html/`.
For SFTP-only hosts, set `FTP_PROTOCOL = sftp` and `FTP_PORT = 22`.
